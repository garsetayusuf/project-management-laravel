<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class TaskController extends Controller
{
    use AuthorizesRequests;

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
     *             @OA\Property(property="tasks", type="array",
     *
     *                 @OA\Items(type="object",
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="project_id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Task title"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="status", type="string", example="pending"),
     *                     @OA\Property(property="priority", type="string", example="medium"),
     *                     @OA\Property(property="due_date", type="string", format="date", example="2025-12-31"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized")
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

        return response()->json(data: [
            'tasks' => $tasks,
        ]);
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
     *         @OA\JsonContent(@OA\Property(property="task", type="object"))
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - not your project"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(rules: [
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'status' => 'sometimes|in:pending,in_progress,done',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'due_date' => 'nullable|date|after_or_equal:today',
        ]);

        $project = Project::findOrFail(id: $validated['project_id']);
        $this->authorize(ability: 'view', arguments: $project);

        $task = $request->user()->tasks()->create($validated);

        return response()->json(data: [
            'message' => 'Task created successfully',
            'task' => $task->load('project'),
        ], status: 201);
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
     *         @OA\JsonContent(@OA\Property(property="task", type="object"))
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Request $request, Task $task): JsonResponse
    {
        $this->authorize(ability: 'view', arguments: $task);

        return response()->json(data: [
            'task' => $task->load(relations: 'project'),
        ]);
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
     *         @OA\JsonContent(@OA\Property(property="task", type="object"))
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        $this->authorize(ability: 'update', arguments: $task);

        $validated = $request->validate(rules: [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'status' => 'sometimes|in:pending,in_progress,done',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'due_date' => 'nullable|date|after_or_equal:today',
        ]);

        $task->update(attributes: $validated);

        return response()->json(data: [
            'message' => 'Task updated successfully',
            'task' => $task->fresh(with: ['project']),
        ]);
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
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Task deleted successfully"))
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->authorize(ability: 'delete', arguments: $task);

        $task->delete();

        return response()->json(data: [
            'message' => 'Task deleted successfully',
        ]);
    }
}
