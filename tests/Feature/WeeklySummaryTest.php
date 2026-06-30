<?php

use App\Models\MacroLog;
use App\Models\User;

it('returns seven days in the weekly summary', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/weekly-summary?start_date=2026-06-10')
        ->assertOk()
        ->assertJsonCount(7, 'days');
});

it('starts from the given start_date and ends six days later', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/weekly-summary?start_date=2026-06-10')
        ->assertOk()
        ->assertJsonFragment(['start_date' => '2026-06-10'])
        ->assertJsonFragment(['end_date'   => '2026-06-16'])
        ->assertJsonPath('days.0.date', '2026-06-10')
        ->assertJsonPath('days.6.date', '2026-06-16');
});

it('includes days with zero totals when no logs exist for those days', function () {
    $user = User::factory()->create();

    // Only one of the seven days has a log
    MacroLog::factory()->for($user)->create(['logged_at' => '2026-06-10']);

    $response = $this->actingAs($user)->getJson('/api/weekly-summary?start_date=2026-06-10')
        ->assertOk();

    $emptyDays = collect($response->json('days'))
        ->filter(fn ($day) => $day['entry_count'] === 0);

    expect($emptyDays)->toHaveCount(6);
});

it('returns correct totals for each day in the weekly summary', function () {
    $user = User::factory()->create();

    MacroLog::factory()->for($user)->create([
        'logged_at' => '2026-06-10',
        'protein_g' => 50,
        'carbs_g'   => 100,
        'fat_g'     => 20,
    ]);
    MacroLog::factory()->for($user)->create([
        'logged_at' => '2026-06-12',
        'protein_g' => 80,
        'carbs_g'   => 120,
        'fat_g'     => 30,
    ]);

    $response = $this->actingAs($user)->getJson('/api/weekly-summary?start_date=2026-06-10')
        ->assertOk();

    $days = collect($response->json('days'))->keyBy('date');

    expect($days['2026-06-10']['total_protein_g'])->toEqual(50);
    expect($days['2026-06-10']['total_carbs_g'])->toEqual(100);
    expect($days['2026-06-10']['total_fat_g'])->toEqual(20);
    expect($days['2026-06-12']['total_protein_g'])->toEqual(80);

    // Day with no logs must be zero
    expect($days['2026-06-11']['entry_count'])->toBe(0);
    expect($days['2026-06-11']['total_protein_g'])->toEqual(0);
});

it('scopes the weekly summary to the authenticated user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    MacroLog::factory()->for($userB)->create([
        'logged_at' => '2026-06-10',
        'protein_g' => 300,
        'carbs_g'   => 400,
        'fat_g'     => 150,
    ]);

    $response = $this->actingAs($userA)->getJson('/api/weekly-summary?start_date=2026-06-10')
        ->assertOk();

    $days = collect($response->json('days'))->keyBy('date');

    expect($days['2026-06-10']['total_protein_g'])->toEqual(0);
    expect($days['2026-06-10']['entry_count'])->toBe(0);
});
