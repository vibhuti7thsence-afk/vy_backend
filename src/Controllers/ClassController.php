<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ClassService;

final class ClassController
{
    public function __construct(
        private readonly ClassService $classService = new ClassService()
    ) {
    }

    public function listAllClasses(): void
    {
        Response::json([
            'success' => true,
            'data'    => $this->classService->listAllClasses(),
        ]);
    }

    public function deleteClass(Request $request): void
    {
        $this->classService->deleteClass($request->body);

        Response::json([
            'success' => true,
            'message' => 'Class deleted successfully.',
        ]);
    }

    public function createClass(Request $request): void
    {
        $result = $this->classService->createClass($request->body);

        Response::json([
            'success' => true,
            'message' => 'Class created successfully.',
            'data' => $result,
        ], 201);
    }

    public function updateClass(Request $request): void
    {
        $result = $this->classService->updateClass($request->body);

        Response::json([
            'success' => true,
            'message' => 'Class updated successfully.',
            'data' => $result,
        ]);
    }
}
