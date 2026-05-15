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
     * List all notification channels for a monitor.
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
     * Add a new notification channel to a monitor.
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
     * Delete a notification channel.
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