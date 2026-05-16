<?php

// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    /**
     * @OA\Get(
     *     path="/api/dashboard",
     *     tags={"Dashboard"},
     *     summary="Get summary stats for the authenticated user",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard summary",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total", type="integer", example=10),
     *                 @OA\Property(property="up", type="integer", example=7),
     *                 @OA\Property(property="down", type="integer", example=1),
     *                 @OA\Property(property="degraded", type="integer", example=1),
     *                 @OA\Property(property="paused", type="integer", example=1),
     *                 @OA\Property(property="overall_uptime_percentage", type="number", example=98.5)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $summary = $this->dashboardService->getSummary($request->user());

        return response()->json(['data' => $summary]);
    }
}