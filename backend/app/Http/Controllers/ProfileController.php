<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
    ) {}

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profileService->update(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'user' => $user,
        ]);
    }
}
