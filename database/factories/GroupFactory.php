<?php

namespace Database\Factories;

use App\Enums\GroupType;
use App\Enums\MembershipMode;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'type' => GroupType::Custom,
            'membership_mode' => MembershipMode::Managed,
            'is_system' => false,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['membership_mode' => MembershipMode::Open]);
    }
}
