<?php

namespace App\Casts;

use App\Enums\TaxonomyKind;
use App\Models\TaxonomyTerm;
use App\Support\Taxonomy;
use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a term key in a string column and reads it back as the term of the
 * record's school (`$event->category->label()`). Accepts a key, a term or
 * one of the built-in enums when writing.
 *
 * @implements CastsAttributes<TaxonomyTerm, string|TaxonomyTerm|BackedEnum>
 */
class AsTaxonomyTerm implements CastsAttributes
{
    /** Never cache the written value: it may be an enum, the reader expects a term. */
    public bool $withoutObjectCaching = true;

    public function __construct(private string $kind) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?TaxonomyTerm
    {
        if ($value === null) {
            return null;
        }

        return app(Taxonomy::class)->resolve(TaxonomyKind::from($this->kind), (string) $value, $attributes['school_id'] ?? null);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value instanceof TaxonomyTerm => $value->key,
            $value instanceof BackedEnum => (string) $value->value,
            $value === null => null,
            default => (string) $value,
        };
    }
}
