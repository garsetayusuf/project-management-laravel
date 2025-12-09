<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class TaskController extends Controller
{
    use ApiResponse, AuthorizesRequests;

    /**
     * Display a listing of tasks
     *
     * @OA\Get(
     *     path="/api/tasks",
     *     operationId="listTasks",
     *     tags={"Tasks"},
     *     summary="List all tasks",
     *     description="Get all tasks with optional filtering by project_id, status, or priority",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="project_id",
     *         in="query",
     *         description="Filter by project ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (pending, in_progress, done)",
     *
     *         @OA\Schema(type="string", enum={"pending","in_progress","done"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="priority",
     *         in="query",
     *         description="Filter by priority (low, medium, high, urgent)",
     *
     *         @OA\Schema(type="string", enum={"low","medium","high","urgent"})
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tasks retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Tasks retrieved successfully"),
     *             @OA\Property(property="statusCode", type="integer", example=200),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="tasks", type="array",
     *
     *                     @OA\Items(type="object",
     *
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="projectId", type="integer", example=1),
     *                         @OA\Property(property="userId", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="Task title"),
     *                         @OA\Property(property="description", type="string"),
     *                         @OA\Property(property="status", type="string", example="pending"),
     *                         @OA\Property(property="priority", type="string", example="medium"),
     *                         @OA\Property(property="dueDate", type="string", format="date", example="2025-12-31"),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Token missing, invalid, or expired"),
     *             @OA\Property(property="error", type="string", example="Unauthorized"),
     *             @OA\Property(property="statusCode", type="integer", example=401),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->tasks()->with('project');

        if ($request->has(key: 'project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has(key: 'status')) {
            $query->where('status', $request->status);
        }

        if ($request->has(key: 'priority')) {
            $query->where('priority', $request->priority);
        }

        $tasks = $query->latest()->get();

        return $this->sendSuccess(data: ['tasks' => $tasks], message: 'Tasks retrieved successfully');
    }

    /**
     * Store a newly created task
     *
     * @OA\Post(
     *     path="/api/tasks",
     *     operationId="createTask",
     *     tags={"Tasks"},
     *     summary="Create a new task",
     *     description="Create a new task in a project",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"project_id","title","description"},
     *
     *             @OA\Property(property="project_id", type="integer", example=1, description="Project ID"),
     *             @OA\Property(property="title", type="string", example="Task title", description="Task title"),
     *             @OA\Property(property="description", type="string", example="Task description"),
     *             @OA\Property(property="status", type="string", enum={"pending","in_progress","done"}, default="pending"),
     *             @OA\Property(property="priority", type="string", enum={"low","medium","high","urgent"}, default="medium"),
     *             @OA\Property(property="due_date", type="string", format="date", example="2025-12-31", description="Due date (optional)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Task created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Task created successfully"),
     *             @OA\Property(property="statusCode", type="integer", example=201),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="task", type="object")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Token missing, invalid, or expired"),
     *             @OA\Property(property="error", type="string", example="Unauthorized"),
     *             @OA\Property(property="statusCode", type="integer", example=401),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - not your project",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="You do not have permission to perform this action"),
     *             @OA\Property(property="error", type="string", example="Forbidden"),
     *             @OA\Property(property="statusCode", type="integer", example=403),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="error", type="string", example="ValidationError"),
     *             @OA\Property(property="statusCode", type="integer", example=422),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $project = Project::findOrFail(id: $validated['project_id']);
        $this->authorize(ability: 'view', arguments: $project);

        $task = $request->user()->tasks()->create($validated);

        return $this->sendSuccess(data: ['task' => $task->load('project')], message: 'Task created successfully', statusCode: 201);
    }

    /**
     * Display the specified task
     *
     * @OA\Get(
     *     path="/api/tasks/{id}",
     *     operationId="showTask",
     *     tags={"Tasks"},
     *     summary="Get a task",
     *     description="Retrieve a specific task",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Task retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Task retrieved successfully"),
     *             @OA\Property(property="statusCode", type="integer", example=200),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="task", type="object")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Token missing, invalid, or expired"),
     *             @OA\Property(property="error", type="string", example="Unauthorized"),
     *             @OA\Property(property="statusCode", type="integer", example=401),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="You do not have permission to perform this action"),
     *             @OA\Property(property="error", type="string", example="Forbidden"),
     *             @OA\Property(property="statusCode", type="integer", example=403),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
     *             @OA\Property(property="error", type="string", example="NotFound"),
     *             @OA\Property(property="statusCode", type="integer", example=404),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function show(Request $request, Task $task): JsonResponse
    {
        $this->authorize(ability: 'view', arguments: $task);

        return $this->sendSuccess(data: ['task' => $task->load(relations: 'project')], message: 'Task retrieved successfully');
    }

    /**
     * Update the specified task
     *
     * @OA\Put(
     *     path="/api/tasks/{id}",
     *     operationId="updateTask",
     *     tags={"Tasks"},
     *     summary="Update a task",
     *     description="Update a specific task",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="status", type="string", enum={"pending","in_progress","done"}),
     *             @OA\Property(property="priority", type="string", enum={"low","medium","high","urgent"}),
     *             @OA\Property(property="due_date", type="string", format="date")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Task updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Task updated successfully"),
     *             @OA\Property(property="statusCode", type="integer", example=200),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="task", type="object")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Token missing, invalid, or expired"),
     *             @OA\Property(property="error", type="string", example="Unauthorized"),
     *             @OA\Property(property="statusCode", type="integer", example=401),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="You do not have permission to perform this action"),
     *             @OA\Property(property="error", type="string", example="Forbidden"),
     *             @OA\Property(property="statusCode", type="integer", example=403),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
     *             @OA\Property(property="error", type="string", example="NotFound"),
     *             @OA\Property(property="statusCode", type="integer", example=404),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize(ability: 'update', arguments: $task);

        $task->update(attributes: $request->validated());

        return $this->sendSuccess(data: ['task' => $task->fresh(with: ['project'])], message: 'Task updated successfully');
    }

    /**
     * Remove the specified task
     *
     * @OA\Delete(
     *     path="/api/tasks/{id}",
     *     operationId="deleteTask",
     *     tags={"Tasks"},
     *     summary="Delete a task",
     *     description="Delete a specific task",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Task deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Task deleted successfully"),
     *             @OA\Property(property="statusCode", type="integer", example=200),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Token missing, invalid, or expired"),
     *             @OA\Property(property="error", type="string", example="Unauthorized"),
     *             @OA\Property(property="statusCode", type="integer", example=401),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="You do not have permission to perform this action"),
     *             @OA\Property(property="error", type="string", example="Forbidden"),
     *             @OA\Property(property="statusCode", type="integer", example=403),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
     *             @OA\Property(property="error", type="string", example="NotFound"),
     *             @OA\Property(property="statusCode", type="integer", example=404),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->authorize(ability: 'delete', arguments: $task);

        $task->delete();

        return $this->sendSuccess(message: 'Task deleted successfully');
    }
}
