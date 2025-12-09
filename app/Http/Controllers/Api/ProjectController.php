<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ProjectController extends Controller
{
    use ApiResponse, AuthorizesRequests;

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
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Projects retrieved successfully"),
     *             @OA\Property(property="statusCode", type="integer", example=200),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="projects", type="array",
     *
     *                     @OA\Items(type="object",
     *
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="userId", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="My Project"),
     *                         @OA\Property(property="description", type="string", example="Project description"),
     *                         @OA\Property(property="tasksCount", type="integer", example=5),
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
        $projects = $request->user()->projects()
            ->withCount('tasks')
            ->latest()
            ->get();

        return $this->sendSuccess(data: ['projects' => $projects], message: 'Projects retrieved successfully');
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
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Project created successfully"),
     *             @OA\Property(property="statusCode", type="integer", example=201),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="project", type="object")
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
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $request->user()->projects()->create($request->validated());

        return $this->sendSuccess(data: ['project' => $project->load('tasks')], message: 'Project created successfully', statusCode: 201);
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
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Project retrieved successfully"),
     *             @OA\Property(property="statusCode", type="integer", example=200),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="project", type="object")
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
     *         response=404,
     *         description="Project not found",
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
    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize(ability: 'view', arguments: $project);

        return $this->sendSuccess(data: ['project' => $project->load(relations: 'tasks')], message: 'Project retrieved successfully');
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
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Project updated successfully"),
     *             @OA\Property(property="statusCode", type="integer", example=200),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="project", type="object")
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
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorize(ability: 'update', arguments: $project);

        $project->update(attributes: $request->validated());

        return $this->sendSuccess(data: ['project' => $project->fresh(with: ['tasks'])], message: 'Project updated successfully');
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
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Project deleted successfully"),
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
    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorize(ability: 'delete', arguments: $project);

        $project->delete();

        return $this->sendSuccess(message: 'Project deleted successfully');
    }
}
