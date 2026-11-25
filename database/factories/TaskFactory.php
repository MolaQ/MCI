<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\School;
use App\Models\Task;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => fn () => app(CurrentSchool::class)->id() ?? School::factory(),
            'created_by' => User::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'is_personal' => true,
            'priority' => TaskPriority::Normal,
            'status' => TaskStatus::Todo,
        ];
    }
}
