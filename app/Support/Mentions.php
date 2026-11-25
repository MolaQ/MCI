<?php

namespace App\Support;

use App\Enums\ProfileVisibility;
use App\Models\Group;
use App\Models\Mention;
use App\Models\School;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Eloquent\Model;

/**
 * System-wide "@user" / "@group" mentions (docs/ARCHITECTURE.md §12.1).
 *
 * The editor stores a mention as
 *   <span data-type="mention" data-id="user:12" data-label="Jan Kowalski">@Jan Kowalski</span>
 *
 * normalize() re-resolves every mention against the database for one school:
 * labels are rewritten from the real record (no forged "@Dyrektor"), and
 * mentions of people or groups outside the school become plain text.
 * Any model with rich text can call normalize() + sync().
 */
class Mentions
{
    /**
     * @return array{html: string, users: list<int>, groups: list<int>}
     */
    public static function normalize(string $html, School $school): array
    {
        $users = [];
        $groups = [];

        if (! str_contains($html, 'data-type="mention"')) {
            return ['html' => $html, 'users' => [], 'groups' => []];
        }

        [$doc, $root] = self::load($html);
        $spans = iterator_to_array((new DOMXPath($doc))->query('//span[@data-type="mention"]'));

        $ids = ['user' => [], 'group' => []];
        foreach ($spans as $span) {
            if (preg_match('/^(user|group):(\d+)$/', (string) $span->getAttribute('data-id'), $m)) {
                $ids[$m[1]][] = (int) $m[2];
            }
        }

        $knownUsers = User::query()->activeInSchool($school)->whereKey(array_unique($ids['user']))->get()->keyBy('id');
        $knownGroups = Group::query()->where('school_id', $school->id)->whereKey(array_unique($ids['group']))->get()->keyBy('id');

        foreach ($spans as $span) {
            /** @var DOMElement $span */
            preg_match('/^(user|group):(\d+)$/', (string) $span->getAttribute('data-id'), $m);
            $entity = match ($m[1] ?? null) {
                'user' => $knownUsers->get((int) $m[2]),
                'group' => $knownGroups->get((int) $m[2]),
                default => null,
            };

            if (! $entity) {
                $span->parentNode->replaceChild($doc->createTextNode($span->textContent), $span);

                continue;
            }

            $label = $entity->name;
            $span->setAttribute('data-label', $label);
            $span->textContent = '@'.$label;
            $entity instanceof User ? $users[] = $entity->id : $groups[] = $entity->id;
        }

        return ['html' => self::save($doc, $root), 'users' => array_values(array_unique($users)), 'groups' => array_values(array_unique($groups))];
    }

    /**
     * Stores the mentions of one piece of content (replacing earlier ones).
     *
     * @param  list<int>  $userIds
     * @param  list<int>  $groupIds
     */
    public static function sync(Model $source, array $userIds, array $groupIds, ?School $school, ?User $actor): void
    {
        $source->morphMany(Mention::class, 'source')->delete();

        $rows = [
            ...array_map(fn ($id) => ['mentionable_type' => 'user', 'mentionable_id' => $id], $userIds),
            ...array_map(fn ($id) => ['mentionable_type' => 'group', 'mentionable_id' => $id], $groupIds),
        ];

        foreach ($rows as $row) {
            $source->morphMany(Mention::class, 'source')->create($row + ['school_id' => $school?->id, 'created_by' => $actor?->id]);
        }
    }

    /**
     * Version for public pages: a person is named only if their own profile
     * setting allows public visibility; otherwise "@…". Groups keep their name.
     */
    public static function forPublic(string $html): string
    {
        if (! str_contains($html, 'data-type="mention"')) {
            return $html;
        }

        [$doc, $root] = self::load($html);
        $spans = iterator_to_array((new DOMXPath($doc))->query('//span[@data-type="mention"]'));
        $userIds = [];
        foreach ($spans as $span) {
            if (preg_match('/^user:(\d+)$/', (string) $span->getAttribute('data-id'), $m)) {
                $userIds[] = (int) $m[1];
            }
        }
        $public = User::query()->whereKey($userIds)->get()
            ->filter(fn (User $u) => $u->effectiveProfileVisibility() === ProfileVisibility::Public)->keyBy('id');

        foreach ($spans as $span) {
            $id = (string) $span->getAttribute('data-id');
            $text = str_starts_with($id, 'user:')
                ? ($public->has((int) substr($id, 5)) ? '@'.$public[(int) substr($id, 5)]->name : '@…')
                : $span->textContent;
            $span->parentNode->replaceChild($doc->createTextNode($text), $span);
        }

        return self::save($doc, $root);
    }

    /**
     * @return array{0: DOMDocument, 1: DOMElement}
     */
    private static function load(string $html): array
    {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="mci-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        return [$doc, $doc->getElementById('mci-root') ?? $doc->getElementsByTagName('div')->item(0)];
    }

    private static function save(DOMDocument $doc, DOMElement $root): string
    {
        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $doc->saveHTML($child);
        }

        return $html;
    }
}
