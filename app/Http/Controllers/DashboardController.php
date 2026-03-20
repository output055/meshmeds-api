<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService)
    {
    }

    public function stats(Request $request)
    {
        return response()->json(
            $this->dashboardService->getStatsForUser($request->user())
        );
    }
}
