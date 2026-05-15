<?php

// app/Http/Controllers/Monitor/MonitorController.php

namespace App\Http\Controllers\Monitor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitor\CreateMonitorRequest;
use App\Http\Requests\Monitor\UpdateMonitorRequest;
use App\Http\Resources\MonitorResource;
use App\Services\MonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MonitorController extends Controller
{
    public function __construct(private readonly MonitorService $monitorService)
    {
    }

    /**
     * List all monitors for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $monitors = $this->monitorService->getAllForUser($request->user());

        return MonitorResource::collection($monitors);
    }

    /**
     * Create a new monitor.
     */
    public function store(CreateMonitorRequest $request): JsonResponse
    {
        $monitor = $this->monitorService->create($request->user(), $request->validated());

        return (new MonitorResource($monitor))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get a single monitor.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $monitor = $this->monitorService->findForUser($request->user(), $id);

        if (! $monitor) {
            return response()->json(['message' => 'Monitor not found.'], 404);
        }

        return (new MonitorResource($monitor))->response();
    }

    /**
     * Update a monitor.
     */
    public function update(UpdateMonitorRequest $request, int $id): JsonResponse
    {
        $monitor = $this->monitorService->findForUser($request->user(), $id);

        if (! $monitor) {
            return response()->json(['message' => 'Monitor not found.'], 404);
        }

        $monitor = $this->monitorService->update($monitor, $request->validated());

        return (new MonitorResource($monitor))->response();
    }

    /**
     * Delete a monitor.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $monitor = $this->monitorService->findForUser($request->user(), $id);

        if (! $monitor) {
            return response()->json(['message' => 'Monitor not found.'], 404);
        }

        $this->monitorService->delete($monitor);

        return response()->json(['message' => 'Monitor deleted successfully.']);
    }
}