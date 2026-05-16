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
     * List all tags for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $tags = $request->user()->tags()->orderBy('name')->get();

        return response()->json([
            'data' => TagResource::collection($tags),
        ]);
    }

    /**
     * Create a new tag.
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
     * Delete a tag.
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
     * Attach a tag to a monitor.
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
     * Detach a tag from a monitor.
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