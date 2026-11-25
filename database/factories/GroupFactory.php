<?php

namespace Database\Factories;

use App\Enums\MembershipMode;
use App\Enums\TermKind;
use App\Models\Group;
use App\Models\School;
use App\Models\Term;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => fn () => app(CurrentSchool::class)->id() ?? School::factory(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'type_id' => fn () => Term::idFor(TermKind::GroupType, 'custom'),
            'membership_mode' => MembershipMode::Managed,
            'is_system' => false,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['membership_mode' => MembershipMode::Open]);
    }
}
