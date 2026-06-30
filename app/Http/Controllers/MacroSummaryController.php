<?php

namespace App\Http\Controllers;

use App\Services\MacroSummaryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MacroSummaryController extends Controller
{
    public function __construct(private MacroSummaryService $summaryService)
    {
    }

    public function daily(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = isset($validated['date'])
            ? Carbon::parse($validated['date'])->format('Y-m-d')
            : now()->format('Y-m-d');

        return response()->json(
            $this->summaryService->dailySummary($request->user(), $date)
        );
    }

    public function weekly(Request $request): JsonResponse
    {
        // Implemented in Step 9
        return response()->json([]);
    }
}
