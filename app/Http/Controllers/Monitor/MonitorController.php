<?php

// app/Http/Controllers/Monitor/MonitorController.php

namespace App\Http\Controllers\Monitor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitor\CreateMonitorRequest;
use App\Http\Requests\Monitor\UpdateMonitorRequest;
use App\Http\Resources\IncidentResource;
use App\Http\Resources\MonitorCheckResource;
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
     * @OA\Get(
     *     path="/api/monitors",
     *     tags={"Monitors"},
     *     summary="List all monitors for the authenticated user",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status", in="query", description="Filter by status", @OA\Schema(type="string", enum={"pending","up","down","degraded","paused"})),
     *     @OA\Parameter(name="search", in="query", description="Search by name or URL", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sort field", @OA\Schema(type="string", enum={"name","created_at","last_checked_at"})),
     *     @OA\Parameter(name="direction", in="query", description="Sort direction", @OA\Schema(type="string", enum={"asc","desc"})),
     *     @OA\Parameter(name="tag", in="query", description="Filter by tag name", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="List of monitors"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $monitors = $this->monitorService->getAllForUser($request->user(), $request->only([
            'status',
            'search',
            'sort',
            'direction',
            'tag',
        ]));

        return MonitorResource::collection($monitors);
    }

    /**
     * @OA\Post(
     *     path="/api/monitors",
     *     tags={"Monitors"},
     *     summary="Register a new URL to monitor",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"url"},
     *             @OA\Property(property="url", type="string", example="https://example.com"),
     *             @OA\Property(property="name", type="string", example="My Website"),
     *             @OA\Property(property="check_interval", type="integer", example=5, minimum=1, maximum=60),
     *             @OA\Property(property="threshold", type="integer", example=3, minimum=1),
     *             @OA\Property(property="response_time_threshold_ms", type="integer", example=2000),
     *             @OA\Property(property="http_method", type="string", enum={"GET","HEAD"}, example="GET"),
     *             @OA\Property(property="follow_redirects", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Monitor created successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(CreateMonitorRequest $request): JsonResponse
    {
        $monitor = $this->monitorService->create($request->user(), $request->validated());

        return (new MonitorResource($monitor))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Get(
     *     path="/api/monitors/{id}",
     *     tags={"Monitors"},
     *     summary="Get a single monitor",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Monitor details"),
     *     @OA\Response(response=404, description="Monitor not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
     * @OA\Put(
     *     path="/api/monitors/{id}",
     *     tags={"Monitors"},
     *     summary="Update a monitor",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Name"),
     *             @OA\Property(property="check_interval", type="integer", example=10),
     *             @OA\Property(property="threshold", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Monitor updated successfully"),
     *     @OA\Response(response=404, description="Monitor not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
     * @OA\Delete(
     *     path="/api/monitors/{id}",
     *     tags={"Monitors"},
     *     summary="Delete a monitor",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Monitor deleted successfully"),
     *     @OA\Response(response=404, description="Monitor not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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

    /**
     * @OA\Post(
     *     path="/api/monitors/{id}/pause",
     *     tags={"Monitors"},
     *     summary="Pause a monitor",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Monitor paused"),
     *     @OA\Response(response=404, description="Monitor not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function pause(Request $request, int $id): JsonResponse
    {
        $monitor = $this->monitorService->findForUser($request->user(), $id);

        if (! $monitor) {
            return response()->json(['message' => 'Monitor not found.'], 404);
        }

        $monitor = $this->monitorService->pause($monitor);

        return (new MonitorResource($monitor))->response();
    }

    /**
     * @OA\Post(
     *     path="/api/monitors/{id}/resume",
     *     tags={"Monitors"},
     *     summary="Resume a paused monitor",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Monitor resumed"),
     *     @OA\Response(response=404, description="Monitor not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function resume(Request $request, int $id): JsonResponse
    {
        $monitor = $this->monitorService->findForUser($request->user(), $id);

        if (! $monitor) {
            return response()->json(['message' => 'Monitor not found.'], 404);
        }

        $monitor = $this->monitorService->resume($monitor);

        return (new MonitorResource($monitor))->response();
    }

    /**
     * @OA\Get(
     *     path="/api/monitors/{id}/history",
     *     tags={"Monitors"},
     *     summary="Get paginated check history for a monitor",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Results per page (max 100)", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer", default=1)),
     *     @OA\Response(response=200, description="Check history"),
     *     @OA\Response(response=404, description="Monitor not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function history(Request $request, int $id): JsonResponse
    {
        $monitor = $this->monitorService->findForUser($request->user(), $id);

        if (! $monitor) {
            return response()->json(['message' => 'Monitor not found.'], 404);
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $checks  = $this->monitorService->getHistory($monitor, $perPage);

        return response()->json([
            'data' => MonitorCheckResource::collection($checks->items()),
            'meta' => [
                'current_page' => $checks->currentPage(),
                'per_page'     => $checks->perPage(),
                'total'        => $checks->total(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/monitors/{id}/history/export",
     *     tags={"Monitors"},
     *     summary="Export check history as CSV",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="CSV file download"),
     *     @OA\Response(response=404, description="Monitor not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function exportHistory(Request $request, int $id): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $monitor = $this->monitorService->findForUser($request->user(), $id);

        if (! $monitor) {
            return response()->json(['message' => 'Monitor not found.'], 404);
        }

        $checks   = $this->monitorService->getHistoryForExport($monitor);
        $filename = "monitor-{$monitor->id}-history.csv";

        return response()->streamDownload(function () use ($checks) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Status Code', 'Response Time (ms)', 'DNS Resolution (ms)', 'Is Up', 'Checked At']);

            foreach ($checks as $check) {
                fputcsv($handle, [
                    $check->id,
                    $check->status_code,
                    $check->response_time_ms,
                    $check->dns_resolution_ms,
                    $check->is_up ? 'true' : 'false',
                    $check->checked_at->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/monitors/{id}/incidents",
     *     tags={"Monitors"},
     *     summary="Get incident history for a monitor",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Incident history"),
     *     @OA\Response(response=404, description="Monitor not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function incidents(Request $request, int $id): JsonResponse
    {
        $monitor = $this->monitorService->findForUser($request->user(), $id);

        if (! $monitor) {
            return response()->json(['message' => 'Monitor not found.'], 404);
        }

        $incidents = $this->monitorService->getIncidents($monitor);

        return response()->json([
            'data' => IncidentResource::collection($incidents),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/monitors/{id}/response-times",
     *     tags={"Monitors"},
     *     summary="Get response time trends for a monitor",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="period", in="query", description="Time period", @OA\Schema(type="string", enum={"24h","7d","30d"}, default="7d")),
     *     @OA\Response(response=200, description="Response time trends"),
     *     @OA\Response(response=404, description="Monitor not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function responseTimes(Request $request, int $id): JsonResponse
    {
        $monitor = $this->monitorService->findForUser($request->user(), $id);

        if (! $monitor) {
            return response()->json(['message' => 'Monitor not found.'], 404);
        }

        $period = in_array($request->query('period'), ['24h', '7d', '30d'])
            ? $request->query('period')
            : '7d';

        $trends = $this->monitorService->getResponseTimeTrends($monitor, $period);

        return response()->json(['data' => $trends]);
    }

    /**
     * @OA\Get(
     *     path="/api/monitors/{id}/daily-stats",
     *     tags={"Monitors"},
     *     summary="Get aggregated daily stats for a monitor",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="days", in="query", description="Number of days (default: 30, max: 90)", @OA\Schema(type="integer", default=30)),
     *     @OA\Response(response=200, description="Daily stats"),
     *     @OA\Response(response=404, description="Monitor not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function dailyStats(Request $request, int $id): JsonResponse
    {
        $monitor = $this->monitorService->findForUser($request->user(), $id);

        if (! $monitor) {
            return response()->json(['message' => 'Monitor not found.'], 404);
        }

        $days  = min((int) $request->query('days', 30), 90);
        $stats = $this->monitorService->getDailyStats($monitor, $days);

        return response()->json(['data' => $stats]);
    }
}
