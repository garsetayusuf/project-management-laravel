<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** LIST PROJECTS TESTS */
it('user can list their own projects', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    Project::factory()->for($user)->count(3)->create();

    $response = $this->getJson('/api/projects', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonCount(3, 'data.projects');
});

it('user only sees their own projects in list', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $otherUser = User::factory()->create();
    Project::factory()->for($user)->count(2)->create();
    Project::factory()->for($otherUser)->count(3)->create();

    $response = $this->getJson('/api/projects', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonCount(2, 'data.projects');
});

it('user cannot list projects without authentication', function () {
    /** @var Tests\TestCase $this */
    $response = $this->getJson('/api/projects');

    $response->assertUnauthorized();
});

it('empty project list returns empty array', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();

    $response = $this->getJson('/api/projects', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonCount(0, 'data.projects');
});

it('project list includes task count', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $project = Project::factory()->for($user)->create();
    $project->tasks()->createMany([
        ['title' => 'Task 1', 'description' => 'Desc', 'user_id' => $user->id],
        ['title' => 'Task 2', 'description' => 'Desc', 'user_id' => $user->id],
        ['title' => 'Task 3', 'description' => 'Desc', 'user_id' => $user->id],
    ]);

    $response = $this->getJson('/api/projects', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonPath('data.projects.0.tasks_count', 3);
});

/** CREATE PROJECT TESTS */
it('user can create project with required fields', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();

    $response = $this->postJson('/api/projects', [
        'name' => 'My Project',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertCreated();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonPath('message', 'Project created successfully');
    $response->assertJsonStructure([
        'status',
        'message',
        'statusCode',
        'data' => [
            'project' => ['id', 'user_id', 'name', 'created_at', 'updated_at'],
        ],
    ]);
    expect(Project::where('name', 'My Project')->exists())->toBeTrue();
});

it('user can create project with optional description', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();

    $response = $this->postJson('/api/projects', [
        'name' => 'My Project',
        'description' => 'Project description',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertCreated();
    expect(Project::where('description', 'Project description')->exists())->toBeTrue();
});

it('project is created with correct user_id', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();

    $this->postJson('/api/projects', [
        'name' => 'My Project',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $project = Project::where('name', 'My Project')->first();
    expect($project->user_id)->toBe($user->id);
});

it('user cannot create project without name', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();

    $response = $this->postJson('/api/projects', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertUnprocessable();
    expect($response->json('data.errors.name'))->not->toBeEmpty();
});

it('user cannot create project with long name', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $longName = str_repeat('a', 256);

    $response = $this->postJson('/api/projects', [
        'name' => $longName,
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertUnprocessable();
    expect($response->json('data.errors.name'))->not->toBeEmpty();
});

it('user cannot create project without authentication', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/projects', [
        'name' => 'My Project',
    ]);

    $response->assertUnauthorized();
});

/** SHOW PROJECT TESTS */
it('user can view their own project', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $project = Project::factory()->for($user)->create();

    $response = $this->getJson("/api/projects/{$project->id}", [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonPath('data.project.id', $project->id);
    $response->assertJsonPath('data.project.name', $project->name);
});

it('user cannot view another users project', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $otherUser = User::factory()->create();
    $project = Project::factory()->for($otherUser)->create();

    $response = $this->getJson("/api/projects/{$project->id}", [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertForbidden();
});

it('show returns 404 for non-existent project', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();

    $response = $this->getJson('/api/projects/99999', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertNotFound();
});

it('project view includes tasks', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $project = Project::factory()->for($user)->create();
    $project->tasks()->createMany([
        ['title' => 'Task 1', 'description' => 'Desc', 'user_id' => $user->id],
        ['title' => 'Task 2', 'description' => 'Desc', 'user_id' => $user->id],
    ]);

    $response = $this->getJson("/api/projects/{$project->id}", [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonCount(2, 'data.project.tasks');
});

/** UPDATE PROJECT TESTS */
it('user can update their own project', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $project = Project::factory()->for($user)->create();

    $response = $this->putJson("/api/projects/{$project->id}", [
        'name' => 'Updated Name',
        'description' => 'Updated description',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonPath('message', 'Project updated successfully');
    expect($project->fresh()->name)->toBe('Updated Name');
    expect($project->fresh()->description)->toBe('Updated description');
});

it('user can update only project name', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $project = Project::factory()->for($user)->create(['description' => 'Original']);

    $response = $this->putJson("/api/projects/{$project->id}", [
        'name' => 'New Name',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    expect($project->fresh()->name)->toBe('New Name');
    expect($project->fresh()->description)->toBe('Original');
});

it('user cannot update another users project', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $otherUser = User::factory()->create();
    $project = Project::factory()->for($otherUser)->create();

    $response = $this->putJson("/api/projects/{$project->id}", [
        'name' => 'Hacked Name',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertForbidden();
    expect($project->fresh()->name)->not->toBe('Hacked Name');
});

it('user cannot update project with invalid data', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $project = Project::factory()->for($user)->create();

    $response = $this->putJson("/api/projects/{$project->id}", [
        'name' => str_repeat('a', 256),
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertUnprocessable();
});

/** DELETE PROJECT TESTS */
it('user can delete their own project', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $project = Project::factory()->for($user)->create();
    $projectId = $project->id;

    $response = $this->deleteJson("/api/projects/{$projectId}", [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['status', 'message', 'statusCode', 'data']);
    $response->assertJsonPath('message', 'Project deleted successfully');
    expect(Project::find($projectId))->toBeNull();
});

it('deleting project deletes all related tasks', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $project = Project::factory()->for($user)->create();
    $project->tasks()->createMany([
        ['title' => 'Task 1', 'description' => 'Desc', 'user_id' => $user->id],
        ['title' => 'Task 2', 'description' => 'Desc', 'user_id' => $user->id],
    ]);

    $taskIds = $project->tasks->pluck('id')->toArray();

    $this->deleteJson("/api/projects/{$project->id}", [], [
        'Authorization' => "Bearer {$token}",
    ]);

    foreach ($taskIds as $taskId) {
        expect(\App\Models\Task::find($taskId))->toBeNull();
    }
});

it('user cannot delete another users project', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();
    $otherUser = User::factory()->create();
    $project = Project::factory()->for($otherUser)->create();

    $response = $this->deleteJson("/api/projects/{$project->id}", [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertForbidden();
    expect(Project::find($project->id))->not->toBeNull();
});

it('delete returns 404 for non-existent project', function () {
    /** @var Tests\TestCase $this */
    [$user, $token] = createUserWithToken();

    $response = $this->deleteJson('/api/projects/99999', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertNotFound();
});
