<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function sendSuccess(mixed $data = null, string $message = 'Operation completed successfully', int $statusCode = 200): JsonResponse
    {
        return response()->json(data: [
            'status' => 'success',
            'data' => $data,
            'message' => $message,
            'statusCode' => $statusCode,
        ], status: $statusCode);
    }

    protected function sendError(string $message = 'An error occurred', int $statusCode = 400, mixed $data = null, string $error = 'Error'): JsonResponse
    {
        return response()->json(data: [
            'status' => 'error',
            'data' => $data,
            'message' => $message,
            'error' => $error,
            'statusCode' => $statusCode,
        ], status: $statusCode);
    }

    protected function sendValidationError(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return response()->json(data: [
            'status' => 'error',
            'data' => ['errors' => $errors],
            'message' => $message,
            'error' => 'ValidationError',
            'statusCode' => 422,
        ], status: 422);
    }

    protected function sendUnauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->sendError(message: $message, statusCode: 401, error: 'Unauthorized');
    }

    protected function sendForbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->sendError(message: $message, statusCode: 403, error: 'Forbidden');
    }

    protected function sendNotFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->sendError(message: $message, statusCode: 404, error: 'NotFound');
    }
}
