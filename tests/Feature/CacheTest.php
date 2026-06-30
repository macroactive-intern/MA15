<?php

use App\Models\MacroLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('runs the aggregation query on the first daily summary request', function () {
    $user = User::factory()->create();
    MacroLog::factory()->for($user)->create(['logged_at' => '2026-06-15']);

    DB::enableQueryLog();

    $this->actingAs($user)->getJson('/api/daily-summary?date=2026-06-15')->assertOk();

    $macroLogQueries = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'macro_logs'));

    expect($macroLogQueries)->not->toBeEmpty();

    DB::disableQueryLog();
});

it('does not run the aggregation query on a second identical request', function () {
    $user = User::factory()->create();
    MacroLog::factory()->for($user)->create(['logged_at' => '2026-06-15']);

    // First request — builds the cache
    $this->actingAs($user)->getJson('/api/daily-summary?date=2026-06-15')->assertOk();

    // Second request — must use cached value
    DB::enableQueryLog();
    $this->actingAs($user)->getJson('/api/daily-summary?date=2026-06-15')->assertOk();

    $macroLogQueries = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'macro_logs'));

    expect($macroLogQueries)->toBeEmpty();

    DB::disableQueryLog();
});

it('scopes the cache key by user and date', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $keyA = "daily-summary:user:{$userA->id}:date:2026-06-15";
    $keyB = "daily-summary:user:{$userB->id}:date:2026-06-15";

    // Only user A requests the summary
    $this->actingAs($userA)->getJson('/api/daily-summary?date=2026-06-15')->assertOk();

    expect(Cache::has($keyA))->toBeTrue();
    expect(Cache::has($keyB))->toBeFalse();
});

it('invalidates the daily summary cache when a macro log is created', function () {
    $user     = User::factory()->create();
    $cacheKey = "daily-summary:user:{$user->id}:date:2026-06-15";

    // Populate the cache
    $this->actingAs($user)->getJson('/api/daily-summary?date=2026-06-15')->assertOk();
    expect(Cache::has($cacheKey))->toBeTrue();

    $this->actingAs($user)->postJson('/api/macro-logs', [
        'logged_at' => '2026-06-15',
        'protein_g' => 50,
        'carbs_g'   => 80,
        'fat_g'     => 20,
    ]);

    expect(Cache::has($cacheKey))->toBeFalse();
});

it('invalidates the daily summary cache when a macro log is updated', function () {
    $user     = User::factory()->create();
    $log      = MacroLog::factory()->for($user)->create(['logged_at' => '2026-06-15']);
    $cacheKey = "daily-summary:user:{$user->id}:date:2026-06-15";

    $this->actingAs($user)->getJson('/api/daily-summary?date=2026-06-15')->assertOk();
    expect(Cache::has($cacheKey))->toBeTrue();

    $this->actingAs($user)->putJson("/api/macro-logs/{$log->id}", [
        'logged_at' => '2026-06-15',
        'protein_g' => 60,
        'carbs_g'   => 90,
        'fat_g'     => 25,
    ]);

    expect(Cache::has($cacheKey))->toBeFalse();
});

it('invalidates the daily summary cache when a macro log is deleted', function () {
    $user     = User::factory()->create();
    $log      = MacroLog::factory()->for($user)->create(['logged_at' => '2026-06-15']);
    $cacheKey = "daily-summary:user:{$user->id}:date:2026-06-15";

    $this->actingAs($user)->getJson('/api/daily-summary?date=2026-06-15')->assertOk();
    expect(Cache::has($cacheKey))->toBeTrue();

    $this->actingAs($user)->deleteJson("/api/macro-logs/{$log->id}");

    expect(Cache::has($cacheKey))->toBeFalse();
});

it('clears both the old and new date cache when logged_at changes', function () {
    $user   = User::factory()->create();
    $log    = MacroLog::factory()->for($user)->create(['logged_at' => '2026-06-15']);
    $oldKey = "daily-summary:user:{$user->id}:date:2026-06-15";
    $newKey = "daily-summary:user:{$user->id}:date:2026-06-16";

    // Populate both date caches
    $this->actingAs($user)->getJson('/api/daily-summary?date=2026-06-15')->assertOk();
    $this->actingAs($user)->getJson('/api/daily-summary?date=2026-06-16')->assertOk();

    expect(Cache::has($oldKey))->toBeTrue();
    expect(Cache::has($newKey))->toBeTrue();

    // Move the log to a different date
    $this->actingAs($user)->putJson("/api/macro-logs/{$log->id}", [
        'logged_at' => '2026-06-16',
        'protein_g' => $log->protein_g,
        'carbs_g'   => $log->carbs_g,
        'fat_g'     => $log->fat_g,
    ]);

    expect(Cache::has($oldKey))->toBeFalse();
    expect(Cache::has($newKey))->toBeFalse();
});

it('recalculates totals on the next request after cache invalidation', function () {
    $user = User::factory()->create();
    $log  = MacroLog::factory()->for($user)->create([
        'logged_at' => '2026-06-15',
        'protein_g' => 100,
        'carbs_g'   => 100,
        'fat_g'     => 10,
    ]);

    // First request — caches the original totals
    $this->actingAs($user)->getJson('/api/daily-summary?date=2026-06-15')
        ->assertOk()
        ->assertJsonFragment(['total_protein_g' => 100]);

    // Update the log — invalidates the cache
    $this->actingAs($user)->putJson("/api/macro-logs/{$log->id}", [
        'logged_at' => '2026-06-15',
        'protein_g' => 200,
        'carbs_g'   => 100,
        'fat_g'     => 10,
    ]);

    // Next request must return the recalculated totals
    $this->actingAs($user)->getJson('/api/daily-summary?date=2026-06-15')
        ->assertOk()
        ->assertJsonFragment(['total_protein_g' => 200]);
});
