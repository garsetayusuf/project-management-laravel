<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** LIST TASKS TESTS */
it('user can list their own tasks', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    Task::factory()->for($project)->for($user)->count(3)->create();

    $response = $this->getJson('/api/tasks', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonCount(3, 'data.tasks');
});

it('user only sees their own tasks in list', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->for($otherUser)->create();
    Task::factory()->for($project)->for($user)->count(2)->create();
    Task::factory()->for($otherProject)->for($otherUser)->count(3)->create();

    $response = $this->getJson('/api/tasks', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonCount(2, 'data.tasks');
});

it('user cannot list tasks without authentication', function () {
    /** @var Tests\TestCase $this */
    $response = $this->getJson('/api/tasks');

    $response->assertUnauthorized();
});

it('empty task list returns empty array', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $response = $this->getJson('/api/tasks', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonCount(0, 'data.tasks');
});

it('user can filter tasks by project_id', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $project2 = Project::factory()->for($user)->create();
    Task::factory()->for($project)->for($user)->count(2)->create();
    Task::factory()->for($project2)->for($user)->count(3)->create();

    $response = $this->getJson("/api/tasks?project_id={$project->id}", [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonCount(2, 'data.tasks');
});

it('user can filter tasks by status', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    Task::factory()->for($project)->for($user)->create(['status' => 'pending']);
    Task::factory()->for($project)->for($user)->count(2)->create(['status' => 'in_progress']);

    $response = $this->getJson('/api/tasks?status=pending', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonCount(1, 'data.tasks');
});

it('user can filter tasks by priority', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    Task::factory()->for($project)->for($user)->create(['priority' => 'high']);
    Task::factory()->for($project)->for($user)->count(2)->create(['priority' => 'low']);

    $response = $this->getJson('/api/tasks?priority=high', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonCount(1, 'data.tasks');
});

it('user can filter tasks by multiple filters', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    Task::factory()->for($project)->for($user)->create(['status' => 'pending', 'priority' => 'high']);
    Task::factory()->for($project)->for($user)->create(['status' => 'pending', 'priority' => 'low']);
    Task::factory()->for($project)->for($user)->create(['status' => 'in_progress', 'priority' => 'high']);

    $response = $this->getJson('/api/tasks?status=pending&priority=high', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonCount(1, 'data.tasks');
});

/** CREATE TASK TESTS */
it('user can create task in their own project', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $response = $this->postJson('/api/tasks', [
        'project_id' => $project->id,
        'title' => 'My Task',
        'description' => 'Task description',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertCreated();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonPath('message', 'Task created successfully');
    $response->assertJsonStructure([
        'status',
        'message',
        'statusCode',
        'data' => [
            'task' => ['id', 'project_id', 'user_id', 'title', 'description'],
        ],
    ]);
    expect(Task::where('title', 'My Task')->exists())->toBeTrue();
});

it('task is created with correct user_id', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $this->postJson('/api/tasks', [
        'project_id' => $project->id,
        'title' => 'My Task',
        'description' => 'Task description',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $task = Task::where('title', 'My Task')->first();
    expect($task->user_id)->toBe($user->id);
});

it('user can create task with optional status', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $response = $this->postJson('/api/tasks', [
        'project_id' => $project->id,
        'title' => 'My Task',
        'description' => 'Task description',
        'status' => 'in_progress',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertCreated();
    expect(Task::where('title', 'My Task')->first()->status)->toBe('in_progress');
});

it('user can create task with optional priority', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $response = $this->postJson('/api/tasks', [
        'project_id' => $project->id,
        'title' => 'My Task',
        'description' => 'Task description',
        'priority' => 'high',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertCreated();
    expect(Task::where('title', 'My Task')->first()->priority)->toBe('high');
});

it('user can create task with optional due_date', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $dueDate = now()->addDays(5)->toDateString();
    $response = $this->postJson('/api/tasks', [
        'project_id' => $project->id,
        'title' => 'My Task',
        'description' => 'Task description',
        'due_date' => $dueDate,
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertCreated();
    $task = Task::where('title', 'My Task')->first();
    expect($task->due_date?->toDateString())->toBe($dueDate);
});

it('task defaults to pending status', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $this->postJson('/api/tasks', [
        'project_id' => $project->id,
        'title' => 'My Task',
        'description' => 'Task description',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $task = Task::where('title', 'My Task')->first();
    expect($task->status)->toBe('pending');
});

it('task defaults to medium priority', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $this->postJson('/api/tasks', [
        'project_id' => $project->id,
        'title' => 'My Task',
        'description' => 'Task description',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $task = Task::where('title', 'My Task')->first();
    expect($task->priority)->toBe('medium');
});

it('user cannot create task without required fields', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $response = $this->postJson('/api/tasks', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertUnprocessable();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data' => ['errors']]);
    expect($response->json('data.errors'))->toHaveKeys(['project_id', 'title', 'description']);
});

it('user cannot create task in another users project', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->for($otherUser)->create();

    $response = $this->postJson('/api/tasks', [
        'project_id' => $otherProject->id,
        'title' => 'Unauthorized Task',
        'description' => 'Should not be created',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertForbidden();
    expect(Task::where('title', 'Unauthorized Task')->exists())->toBeFalse();
});

it('user cannot create task with invalid status', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $response = $this->postJson('/api/tasks', [
        'project_id' => $project->id,
        'title' => 'My Task',
        'description' => 'Task description',
        'status' => 'invalid_status',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertUnprocessable();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data' => ['errors']]);
    expect($response->json('data.errors.status'))->not->toBeEmpty();
});

it('user cannot create task with invalid priority', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $response = $this->postJson('/api/tasks', [
        'project_id' => $project->id,
        'title' => 'My Task',
        'description' => 'Task description',
        'priority' => 'invalid_priority',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertUnprocessable();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data' => ['errors']]);
    expect($response->json('data.errors.priority'))->not->toBeEmpty();
});

it('user cannot create task with past due_date', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $pastDate = now()->subDays(5)->toDateString();
    $response = $this->postJson('/api/tasks', [
        'project_id' => $project->id,
        'title' => 'My Task',
        'description' => 'Task description',
        'due_date' => $pastDate,
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertUnprocessable();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data' => ['errors']]);
    expect($response->json('data.errors.due_date'))->not->toBeEmpty();
});

/** SHOW TASK TESTS */
it('user can view their own task', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $task = Task::factory()->for($project)->for($user)->create();

    $response = $this->getJson("/api/tasks/{$task->id}", [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonPath('data.task.id', $task->id);
    $response->assertJsonPath('data.task.title', $task->title);
});

it('user cannot view another users task', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->for($otherUser)->create();
    $task = Task::factory()->for($otherProject)->for($otherUser)->create();

    $response = $this->getJson("/api/tasks/{$task->id}", [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertForbidden();
});

it('show returns 404 for non-existent task', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $response = $this->getJson('/api/tasks/99999', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertNotFound();
});

/** UPDATE TASK TESTS */
it('user can update their own task', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $task = Task::factory()->for($project)->for($user)->create();

    $response = $this->putJson("/api/tasks/{$task->id}", [
        'title' => 'Updated Task',
        'description' => 'Updated description',
        'status' => 'in_progress',
        'priority' => 'high',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonPath('message', 'Task updated successfully');
    expect($task->fresh()->title)->toBe('Updated Task');
    expect($task->fresh()->status)->toBe('in_progress');
    expect($task->fresh()->priority)->toBe('high');
});

it('user can partially update task', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $task = Task::factory()->for($project)->for($user)->create([
        'title' => 'Original Title',
        'status' => 'pending',
    ]);

    $this->putJson("/api/tasks/{$task->id}", [
        'status' => 'done',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    expect($task->fresh()->title)->toBe('Original Title');
    expect($task->fresh()->status)->toBe('done');
});

it('user cannot update another users task', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->for($otherUser)->create();
    $task = Task::factory()->for($otherProject)->for($otherUser)->create();

    $response = $this->putJson("/api/tasks/{$task->id}", [
        'title' => 'Hacked Title',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertForbidden();
    expect($task->fresh()->title)->not->toBe('Hacked Title');
});

it('user cannot update task with invalid status', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $task = Task::factory()->for($project)->for($user)->create();

    $response = $this->putJson("/api/tasks/{$task->id}", [
        'status' => 'invalid_status',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertUnprocessable();
});

/** DELETE TASK TESTS */
it('user can delete their own task', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $task = Task::factory()->for($project)->for($user)->create();
    $taskId = $task->id;

    $response = $this->deleteJson("/api/tasks/{$taskId}", [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonPath('message', 'Task deleted successfully');
    expect(Task::find($taskId))->toBeNull();
});

it('user cannot delete another users task', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->for($otherUser)->create();
    $task = Task::factory()->for($otherProject)->for($otherUser)->create();

    $response = $this->deleteJson("/api/tasks/{$task->id}", [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertForbidden();
    expect(Task::find($task->id))->not->toBeNull();
});

it('delete returns 404 for non-existent task', function () {
    /** @var Tests\TestCase $this */
    [$user, $token, $project] = createUserWithTokenAndProject();
    $response = $this->deleteJson('/api/tasks/99999', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertNotFound();
});
