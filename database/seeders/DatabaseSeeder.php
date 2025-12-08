<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()
            ->for($user)
            ->create();

        Task::factory()
            ->for($project)
            ->for($user)
            ->create([
                'status' => 'pending',
                'priority' => 'medium',
                'due_date' => now()->addDays(7),
            ]);

        Task::factory()
            ->for($project)
            ->for($user)
            ->create([
                'status' => fake()->randomElement(['in_progress', 'done']),
                'priority' => fake()->randomElement(['high', 'urgent']),
            ]);
    }
}
