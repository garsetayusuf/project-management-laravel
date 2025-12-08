<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function getTokenForUser(User $user): string
{
    return app(JWTService::class)->generateAccessToken($user);
}

/** PROJECT AUTHORIZATION TESTS */
it('user can view their own project', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $token = app(JWTService::class)->generateAccessToken($user);

    $response = $this->getJson('/api/projects/'.$project->id, [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertOk();
    $response->assertJsonPath('project.id', $project->id);
});

it('user cannot view another users project', function () {
    /** @var Tests\TestCase $this */
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $project = Project::factory()->for($user1)->create();
    $token = getTokenForUser($user2);

    $response = $this->getJson('/api/projects/'.$project->id, [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertForbidden();
});

it('user can update their own project', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $token = app(JWTService::class)->generateAccessToken($user);

    $response = $this->putJson('/api/projects/'.$project->id, [
        'name' => 'Updated Project Name',
    ], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertOk();
    expect($project->fresh()->name)->toBe('Updated Project Name');
});

it('user cannot update another users project', function () {
    /** @var Tests\TestCase $this */
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $project = Project::factory()->for($user1)->create();
    $originalName = $project->name;
    $token = getTokenForUser($user2);

    $response = $this->putJson('/api/projects/'.$project->id, [
        'name' => 'Hacked Name',
    ], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertForbidden();
    expect($project->fresh()->name)->toBe($originalName);
});

it('user can delete their own project', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $projectId = $project->id;
    $token = app(JWTService::class)->generateAccessToken($user);

    $response = $this->deleteJson('/api/projects/'.$projectId, [], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertOk();
    expect(Project::find($projectId))->toBeNull();
});

it('user cannot delete another users project', function () {
    /** @var Tests\TestCase $this */
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $project = Project::factory()->for($user1)->create();
    $token = getTokenForUser($user2);

    $response = $this->deleteJson('/api/projects/'.$project->id, [], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertForbidden();
    expect(Project::find($project->id))->not->toBeNull();
});

/** TASK AUTHORIZATION TESTS */
it('user can view their own task', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->for($project)->for($user)->create();
    $token = app(JWTService::class)->generateAccessToken($user);

    $response = $this->getJson('/api/tasks/'.$task->id, [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertOk();
    $response->assertJsonPath('task.id', $task->id);
});

it('user cannot view another users task', function () {
    /** @var Tests\TestCase $this */
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $project = Project::factory()->for($user1)->create();
    $task = Task::factory()->for($project)->for($user1)->create();
    $token = getTokenForUser($user2);

    $response = $this->getJson('/api/tasks/'.$task->id, [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertForbidden();
});

it('user can update their own task', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->for($project)->for($user)->create();
    $token = app(JWTService::class)->generateAccessToken($user);

    $response = $this->putJson('/api/tasks/'.$task->id, [
        'title' => 'Updated Task',
    ], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertOk();
    expect($task->fresh()->title)->toBe('Updated Task');
});

it('user cannot update another users task', function () {
    /** @var Tests\TestCase $this */
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $project = Project::factory()->for($user1)->create();
    $task = Task::factory()->for($project)->for($user1)->create();
    $originalTitle = $task->title;
    $token = getTokenForUser($user2);

    $response = $this->putJson('/api/tasks/'.$task->id, [
        'title' => 'Hacked Task',
    ], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertForbidden();
    expect($task->fresh()->title)->toBe($originalTitle);
});

it('user can delete their own task', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->for($project)->for($user)->create();
    $taskId = $task->id;
    $token = app(JWTService::class)->generateAccessToken($user);

    $response = $this->deleteJson('/api/tasks/'.$taskId, [], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertOk();
    expect(Task::find($taskId))->toBeNull();
});

it('user cannot delete another users task', function () {
    /** @var Tests\TestCase $this */
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $project = Project::factory()->for($user1)->create();
    $task = Task::factory()->for($project)->for($user1)->create();
    $token = getTokenForUser($user2);

    $response = $this->deleteJson('/api/tasks/'.$task->id, [], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertForbidden();
    expect(Task::find($task->id))->not->toBeNull();
});

it('user cannot create task in another users project', function () {
    /** @var Tests\TestCase $this */
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $project = Project::factory()->for($user1)->create();
    $token = getTokenForUser($user2);

    $response = $this->postJson('/api/tasks', [
        'project_id' => $project->id,
        'title' => 'Unauthorized Task',
        'description' => 'Should not be created',
    ], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertForbidden();
    expect(Task::where('title', 'Unauthorized Task')->exists())->toBeFalse();
});

it('user can create task in their own project', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $token = app(JWTService::class)->generateAccessToken($user);

    $response = $this->postJson('/api/tasks', [
        'project_id' => $project->id,
        'title' => 'My Task',
        'description' => 'Task description',
    ], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertCreated();
    expect(Task::where('title', 'My Task')->where('project_id', $project->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

/** LIST ONLY USER'S OWN RESOURCES */
it('user only sees their own projects', function () {
    /** @var Tests\TestCase $this */
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    Project::factory()->for($user1)->count(3)->create();
    Project::factory()->for($user2)->count(2)->create();
    $token = getTokenForUser($user1);

    $response = $this->getJson('/api/projects', [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertOk();
    $response->assertJsonCount(3, 'projects');
});

it('user only sees their own tasks', function () {
    /** @var Tests\TestCase $this */
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $project1 = Project::factory()->for($user1)->create();
    $project2 = Project::factory()->for($user2)->create();
    Task::factory()->for($project1)->for($user1)->count(3)->create();
    Task::factory()->for($project2)->for($user2)->count(2)->create();
    $token = getTokenForUser($user1);

    $response = $this->getJson('/api/tasks', [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertOk();
    $response->assertJsonCount(3, 'tasks');
});
