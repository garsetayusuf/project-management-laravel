<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ProjectController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the user's projects
     *
     * @OA\Get(
     *     path="/api/projects",
     *     operationId="listProjects",
     *     tags={"Projects"},
     *     summary="List all projects",
     *     description="Get all projects belonging to the authenticated user",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Projects retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="projects", type="array",
     *
     *                 @OA\Items(type="object",
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="My Project"),
     *                     @OA\Property(property="description", type="string", example="Project description"),
     *                     @OA\Property(property="tasks_count", type="integer", example=5),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $projects = $request->user()->projects()
            ->withCount('tasks')
            ->latest()
            ->get();

        return response()->json(data: [
            'projects' => $projects,
        ]);
    }

    /**
     * Store a newly created project
     *
     * @OA\Post(
     *     path="/api/projects",
     *     operationId="createProject",
     *     tags={"Projects"},
     *     summary="Create a new project",
     *     description="Create a new project for the authenticated user",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name"},
     *
     *             @OA\Property(property="name", type="string", example="My Project", description="Project name"),
     *             @OA\Property(property="description", type="string", example="Project description", description="Project description (optional)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Project created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Project created successfully"),
     *             @OA\Property(property="project", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(rules: [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $project = $request->user()->projects()->create($validated);

        return response()->json(data: [
            'message' => 'Project created successfully',
            'project' => $project->load('tasks'),
        ], status: 201);
    }

    /**
     * Display the specified project
     *
     * @OA\Get(
     *     path="/api/projects/{id}",
     *     operationId="showProject",
     *     tags={"Projects"},
     *     summary="Get a project",
     *     description="Retrieve a specific project with all its tasks",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Project ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Project retrieved successfully",
     *
     *         @OA\JsonContent(@OA\Property(property="project", type="object"))
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - not your project"),
     *     @OA\Response(response=404, description="Project not found")
     * )
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize(ability: 'view', arguments: $project);

        return response()->json(data: [
            'project' => $project->load(relations: 'tasks'),
        ]);
    }

    /**
     * Update the specified project
     *
     * @OA\Put(
     *     path="/api/projects/{id}",
     *     operationId="updateProject",
     *     tags={"Projects"},
     *     summary="Update a project",
     *     description="Update a specific project",
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
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Project updated successfully",
     *
     *         @OA\JsonContent(@OA\Property(property="project", type="object"))
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize(ability: 'update', arguments: $project);

        $validated = $request->validate(rules: [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $project->update(attributes: $validated);

        return response()->json(data: [
            'message' => 'Project updated successfully',
            'project' => $project->fresh(with: ['tasks']),
        ]);
    }

    /**
     * Remove the specified project
     *
     * @OA\Delete(
     *     path="/api/projects/{id}",
     *     operationId="deleteProject",
     *     tags={"Projects"},
     *     summary="Delete a project",
     *     description="Delete a specific project and all its tasks",
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
     *         description="Project deleted successfully",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Project deleted successfully"))
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorize(ability: 'delete', arguments: $project);

        $project->delete();

        return response()->json(data: [
            'message' => 'Project deleted successfully',
        ]);
    }
}
