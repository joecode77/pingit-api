<?php

// app/Http/Controllers/Tag/TagController.php

namespace App\Http\Controllers\Tag;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tag\CreateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Services\MonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function __construct(private readonly MonitorService $monitorService)
    {
    }

    /**
     * @OA\Get(
     *     path="/api/tags",
     *     tags={"Tags"},
     *     summary="List all tags for the authenticated user",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="List of tags"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $tags = $request->user()->tags()->orderBy('name')->get();

        return response()->json([
            'data' => TagResource::collection($tags),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/tags",
     *     tags={"Tags"},
     *     summary="Create a new tag",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="production")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tag created"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(CreateTagRequest $request): JsonResponse
    {
        $tag = $request->user()->tags()->create([
            'name' => $request->validated('name'),
        ]);

        return (new TagResource($tag))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Delete(
     *     path="/api/tags/{id}",
     *     tags={"Tags"},
     *     summary="Delete a tag",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Tag deleted"),
     *     @OA\Response(response=404, description="Tag not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tag = $request->user()->tags()->find($id);

        if (! $tag) {
            return response()->json(['message' => 'Tag not found.'], 404);
        }

        $tag->delete();

        return response()->json(['message' => 'Tag deleted successfully.']);
    }

    /**
     * @OA\Post(
     *     path="/api/monitors/{monitorId}/tags",
     *     tags={"Tags"},
     *     summary="Attach a tag to a monitor",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="monitorId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"tag_id"},
     *             @OA\Property(property="tag_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Tag attached successfully"),
     *     @OA\Response(response=404, description="Monitor or tag not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function attach(Request $request, int $monitorId): JsonResponse
    {
        $monitor = $this->monitorService->findForUser($request->user(), $monitorId);

        if (! $monitor) {
            return response()->json(['message' => 'Monitor not found.'], 404);
        }

        $tag = $request->user()->tags()->find($request->input('tag_id'));

        if (! $tag) {
            return response()->json(['message' => 'Tag not found.'], 404);
        }

        $monitor->tags()->syncWithoutDetaching([$tag->id]);

        return response()->json(['message' => 'Tag attached successfully.']);
    }

    /**
     * @OA\Delete(
     *     path="/api/monitors/{monitorId}/tags/{tagId}",
     *     tags={"Tags"},
     *     summary="Detach a tag from a monitor",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="monitorId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="tagId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Tag detached successfully"),
     *     @OA\Response(response=404, description="Monitor not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function detach(Request $request, int $monitorId, int $tagId): JsonResponse
    {
        $monitor = $this->monitorService->findForUser($request->user(), $monitorId);

        if (! $monitor) {
            return response()->json(['message' => 'Monitor not found.'], 404);
        }

        $monitor->tags()->detach($tagId);

        return response()->json(['message' => 'Tag detached successfully.']);
    }
}