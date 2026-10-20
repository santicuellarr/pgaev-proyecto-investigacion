<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreForumPostRequest;
use App\Models\Activity;
use App\Services\ForumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForumPostController extends Controller
{
    public function __construct(
        private readonly ForumService $forumService,
    ) {}

    public function index(Request $request, Activity $activity): JsonResponse
    {
        return response()->json([
            'posts' => $this->forumService->listPosts($activity, $request->user()),
        ]);
    }

    public function store(StoreForumPostRequest $request, Activity $activity): JsonResponse
    {
        $result = $this->forumService->createPost(
            $activity,
            $request->user(),
            $request->validated('body'),
            $request->validated('parent_id'),
        );

        return response()->json($result, 201);
    }
}
