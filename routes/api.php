<?php

use App\Http\Controllers\MacroLogController;
use App\Http\Controllers\MacroSummaryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/macro-logs', [MacroLogController::class, 'store']);
    Route::put('/macro-logs/{macroLog}', [MacroLogController::class, 'update']);
    Route::delete('/macro-logs/{macroLog}', [MacroLogController::class, 'destroy']);

    Route::get('/daily-summary', [MacroSummaryController::class, 'daily']);
    Route::get('/weekly-summary', [MacroSummaryController::class, 'weekly']);
});
