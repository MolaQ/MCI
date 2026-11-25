<?php

namespace App\Policies;

use App\Domain\Taxonomy\TermCatalog;
use App\Enums\Permission;
use App\Enums\TermKind;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Support\CurrentSchool;

/**
 * Portal-wide entries belong to site admins. A school's own entries belong
 * to people with categories.manage in that (active) school, and only while
 * the kind's feature switch is on for the school.
 */
class TermPolicy
{
    public function __construct(private TermCatalog $catalog, private CurrentSchool $current) {}

    /** Open the management screen for a school (null = the site screen). */
    public function manage(User $actor, ?School $school = null): bool
    {
        if ($school === null) {
            return (bool) $actor->is_site_admin;
        }

        return $this->current->id() === $school->id && $actor->can(Permission::CategoriesManage->value);
    }

    public function create(User $actor, TermKind $kind, ?School $school = null): bool
    {
        return $school === null
            ? (bool) $actor->is_site_admin
            : $this->manage($actor, $school) && $this->catalog->customAllowed($kind, $school);
    }

    public function update(User $actor, Term $term): bool
    {
        if ($term->isGlobal()) {
            return (bool) $actor->is_site_admin;
        }

        return $this->current->id() === $term->school_id
            && $actor->can(Permission::CategoriesManage->value)
            && $this->catalog->customAllowed($term->kind, $this->current->get());
    }

    public function delete(User $actor, Term $term): bool
    {
        return $this->update($actor, $term) && ! $this->catalog->isProtected($term);
    }

    /** Hide / show a portal-wide entry in one school. */
    public function hide(User $actor, Term $term, School $school): bool
    {
        return $term->isGlobal()
            && ! $this->catalog->isProtected($term)
            && $this->create($actor, $term->kind, $school);
    }
}
