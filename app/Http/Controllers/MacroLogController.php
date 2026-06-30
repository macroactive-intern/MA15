<?php

namespace App\Http\Controllers;

use App\Models\MacroLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MacroLogController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'logged_at'   => ['required', 'date'],
            'protein_g'   => ['required', 'numeric', 'min:0'],
            'carbs_g'     => ['required', 'numeric', 'min:0'],
            'fat_g'       => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:150'],
        ]);

        $log = $request->user()->macroLogs()->create($validated);

        return response()->json($log, 201);
    }

    public function update(Request $request, MacroLog $macroLog): JsonResponse
    {
        if ($macroLog->user_id !== $request->user()->id) {
            abort(404);
        }

        $validated = $request->validate([
            'logged_at'   => ['required', 'date'],
            'protein_g'   => ['required', 'numeric', 'min:0'],
            'carbs_g'     => ['required', 'numeric', 'min:0'],
            'fat_g'       => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:150'],
        ]);

        $macroLog->update($validated);

        return response()->json($macroLog);
    }

    public function destroy(Request $request, MacroLog $macroLog): Response
    {
        if ($macroLog->user_id !== $request->user()->id) {
            abort(404);
        }

        $macroLog->delete();

        return response()->noContent();
    }
}
