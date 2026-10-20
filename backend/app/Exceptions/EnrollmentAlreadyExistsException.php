<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentAlreadyExistsException extends Exception
{
    public function __construct(string $message = 'Ya estás inscrito en este curso.')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [],
        ], 422);
    }
}
