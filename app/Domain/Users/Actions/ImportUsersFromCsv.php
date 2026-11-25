<?php

namespace App\Domain\Users\Actions;

use App\Enums\GroupRole;
use App\Enums\Role;
use App\Models\Group;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Bulk provisioning from a CSV file (Excel "CSV UTF-8" export works).
 *
 * Columns (header row required, Polish or English names, ; or , separated):
 *   imie|first_name, nazwisko|last_name, email, rola|role, grupy|groups, telefon|phone
 *
 * "grupy" lists group names or slugs separated by "|". Append ":lider" (or
 * ":leader") to make the person a leader of that group, e.g.
 *   Matematyka:lider|Wychowawcy|Wolontariat
 */
class ImportUsersFromCsv
{
    private const HEADER_ALIASES = [
        'imie' => 'first_name', 'imię' => 'first_name', 'first_name' => 'first_name', 'firstname' => 'first_name',
        'nazwisko' => 'last_name', 'last_name' => 'last_name', 'lastname' => 'last_name',
        'email' => 'email', 'e-mail' => 'email', 'mail' => 'email',
        'rola' => 'role', 'role' => 'role', 'role_name' => 'role',
        'grupy' => 'groups', 'groups' => 'groups', 'grupa' => 'groups',
        'telefon' => 'phone', 'phone' => 'phone', 'tel' => 'phone',
    ];

    public function __construct(
        private SaveUser $saveUser,
        private SendAccountSetupLink $sendSetupLink,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int, errors: array<int, string>, rows: int}
     */
    public function handle(
        string $path,
        bool $dryRun = true,
        bool $updateExisting = false,
        bool $sendSetupLinks = false,
        string $defaultRole = 'teacher',
    ): array {
        $rows = $this->parse($path);
        $groupIndex = $this->groupIndex();
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [], 'rows' => count($rows)];
        $seenEmails = [];
        $prepared = [];

        foreach ($rows as $line => $row) {
            $row['email'] = mb_strtolower(trim($row['email'] ?? ''));
            $row['role'] = Str::lower(trim($row['role'] ?? '')) ?: $defaultRole;

            $validator = Validator::make($row, [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:191'],
                'phone' => ['nullable', 'string', 'max:32'],
                'role' => ['required', 'in:'.implode(',', array_column(Role::cases(), 'value'))],
            ]);

            if ($validator->fails()) {
                $result['errors'][$line] = implode(' ', $validator->errors()->all());

                continue;
            }

            if (isset($seenEmails[$row['email']])) {
                $result['errors'][$line] = __('Zduplikowany adres e-mail w pliku (wiersz :line).', ['line' => $seenEmails[$row['email']]]);

                continue;
            }
            $seenEmails[$row['email']] = $line;

            [$groups, $unknown] = $this->resolveGroups($row['groups'] ?? '', $groupIndex);
            if ($unknown) {
                $result['errors'][$line] = __('Nieznane grupy: :groups', ['groups' => implode(', ', $unknown)]);

                continue;
            }

            $existing = User::withTrashed()->where('email', $row['email'])->first();
            if ($existing && ! $updateExisting) {
                $result['skipped']++;

                continue;
            }

            $prepared[] = [$row, $groups, $existing];
            $existing ? $result['updated']++ : $result['created']++;
        }

        if ($dryRun) {
            return $result;
        }

        $toNotify = [];

        DB::transaction(function () use ($prepared, &$toNotify) {
            foreach ($prepared as [$row, $groups, $existing]) {
                $data = [
                    'first_name' => trim($row['first_name']),
                    'last_name' => trim($row['last_name']),
                    'email' => $row['email'],
                    'phone' => trim($row['phone'] ?? '') ?: null,
                    'roles' => [$row['role']],
                ];

                if ($existing) {
                    // Import adds memberships; it never removes ones made in the app.
                    $groups = $existing->groups()->get()
                        ->mapWithKeys(fn ($g) => [$g->id => $g->pivot->role->value])
                        ->replace($groups)
                        ->all();
                }

                $data['groups'] = $groups;

                if ($existing?->trashed()) {
                    $existing->restore();
                }

                $user = $this->saveUser->handle($data, $existing);

                if (! $existing) {
                    $toNotify[] = $user;
                }
            }
        });

        if ($sendSetupLinks) {
            foreach ($toNotify as $user) {
                $this->sendSetupLink->handle($user);
            }
        }

        Audit::log('users.imported', null, [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'errors' => count($result['errors']),
        ]);

        return $result;
    }

    /**
     * @return array<int, array<string, string>> keyed by 1-based file line
     */
    public function parse(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Cannot read CSV file.');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // strip UTF-8 BOM
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1250');
        }

        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        $headerLine = array_shift($lines) ?? '';
        $delimiter = substr_count($headerLine, ';') >= substr_count($headerLine, ',') ? ';' : ',';

        $header = array_map(
            fn ($h) => self::HEADER_ALIASES[Str::lower(trim($h))] ?? null,
            str_getcsv($headerLine, $delimiter, '"', ''),
        );

        $rows = [];
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line, $delimiter, '"', '');
            $row = [];
            foreach ($header as $col => $key) {
                if ($key !== null) {
                    $row[$key] = trim($values[$col] ?? '');
                }
            }
            $rows[$i + 2] = $row;
        }

        return $rows;
    }

    /**
     * @return array<string, int>
     */
    private function groupIndex(): array
    {
        $index = [];
        foreach (Group::query()->get(['id', 'name', 'slug']) as $group) {
            $index[Str::lower($group->name)] = $group->id;
            $index[Str::lower($group->slug)] = $group->id;
        }

        return $index;
    }

    /**
     * @param  array<string, int>  $index
     * @return array{0: array<int, string>, 1: list<string>}
     */
    private function resolveGroups(string $value, array $index): array
    {
        $groups = [];
        $unknown = [];

        foreach (array_filter(array_map('trim', explode('|', $value))) as $entry) {
            $role = GroupRole::Member;
            if (preg_match('/^(.*):(lider|leader)$/iu', $entry, $m)) {
                $entry = trim($m[1]);
                $role = GroupRole::Leader;
            }

            $id = $index[Str::lower($entry)] ?? null;
            if ($id === null) {
                $unknown[] = $entry;

                continue;
            }

            $groups[$id] = $role->value;
        }

        return [$groups, $unknown];
    }
}
