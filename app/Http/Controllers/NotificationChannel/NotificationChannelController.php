<?php

// app/Http/Controllers/NotificationChannel/NotificationChannelController.php

namespace App\Http\Controllers\NotificationChannel;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationChannel\CreateNotificationChannelRequest;
use App\Http\Resources\NotificationChannelResource;
use App\Models\NotificationChannel;
use App\Services\MonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationChannelController extends Controller
{
    public function __construct(private readonly MonitorService $monitorService)
    {
    }

    /**
     * @OA\Get(
     *     path="/api/monitors/{monitorId}/channels",
     *     tags={"Notification Channels"},
     *     summary="List all notification channels for a monitor",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="monitorId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="List of notification channels"),
     *     @OA\Response(response=404, description="Monitor not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request, int $monitorId): JsonResponse
    {
        $monitor = $this->monitorService->findForUser($request->user(), $monitorId);

        if (! $monitor) {
            return response()->json(['message' => 'Monitor not found.'], 404);
        }

        $channels = $monitor->notificationChannels()->get();

        return response()->json([
            'data' => NotificationChannelResource::collection($channels),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/monitors/{monitorId}/channels",
     *     tags={"Notification Channels"},
     *     summary="Add a notification channel to a monitor",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="monitorId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type","value"},
     *             @OA\Property(property="type", type="string", enum={"email","webhook"}, example="email"),
     *             @OA\Property(property="value", type="string", example="alerts@example.com"),
     *             @OA\Property(property="notify_on_down", type="boolean", example=true),
     *             @OA\Property(property="notify_on_recovery", type="boolean", example=true),
     *             @OA\Property(property="notify_on_degraded", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Notification channel created"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=404, description="Monitor not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(CreateNotificationChannelRequest $request, int $monitorId): JsonResponse
    {
        $monitor = $this->monitorService->findForUser($request->user(), $monitorId);

        if (! $monitor) {
            return response()->json(['message' => 'Monitor not found.'], 404);
        }

        $channel = $monitor->notificationChannels()->create([
            'type'               => $request->validated('type'),
            'value'              => $request->validated('value'),
            'notify_on_down'     => $request->validated('notify_on_down', true),
            'notify_on_recovery' => $request->validated('notify_on_recovery', true),
            'notify_on_degraded' => $request->validated('notify_on_degraded', false),
        ]);

        return (new NotificationChannelResource($channel))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Delete(
     *     path="/api/monitors/{monitorId}/channels/{channelId}",
     *     tags={"Notification Channels"},
     *     summary="Delete a notification channel",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="monitorId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="channelId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Notification channel deleted"),
     *     @OA\Response(response=404, description="Monitor or channel not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy(Request $request, int $monitorId, int $channelId): JsonResponse
    {
        $monitor = $this->monitorService->findForUser($request->user(), $monitorId);

        if (! $monitor) {
            return response()->json(['message' => 'Monitor not found.'], 404);
        }

        $channel = $monitor->notificationChannels()->find($channelId);

        if (! $channel) {
            return response()->json(['message' => 'Notification channel not found.'], 404);
        }

        $channel->delete();

        return response()->json(['message' => 'Notification channel deleted successfully.']);
    }
}