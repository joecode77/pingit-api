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
     * Return summary stats for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $summary = $this->dashboardService->getSummary($request->user());

        return response()->json(['data' => $summary]);
    }
}