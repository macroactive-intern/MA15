<?php

use App\Models\MacroLog;
use App\Models\User;

it('returns correct aggregated totals for a date', function () {
    $user = User::factory()->create();

    MacroLog::factory()->for($user)->create([
        'logged_at' => '2026-06-15',
        'protein_g' => 100,
        'carbs_g'   => 150,
        'fat_g'     => 40,
    ]);
    MacroLog::factory()->for($user)->create([
        'logged_at' => '2026-06-15',
        'protein_g' => 65,
        'carbs_g'   => 70,
        'fat_g'     => 30,
    ]);

    // protein: 165, carbs: 220, fat: 70
    // calories: (165×4) + (220×4) + (70×9) = 660 + 880 + 630 = 2170
    $this->actingAs($user)->getJson('/api/daily-summary?date=2026-06-15')
        ->assertOk()
        ->assertJson([
            'date'            => '2026-06-15',
            'total_protein_g' => 165,
            'total_carbs_g'   => 220,
            'total_fat_g'     => 70,
            'total_calories'  => 2170,
            'entry_count'     => 2,
        ]);
});

it('defaults to today when no date is provided', function () {
    $user  = User::factory()->create();
    $today = now()->format('Y-m-d');

    $this->actingAs($user)->getJson('/api/daily-summary')
        ->assertOk()
        ->assertJsonFragment(['date' => $today]);
});

it('returns zero totals for a day with no macro logs', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/daily-summary?date=2026-06-15')
        ->assertOk()
        ->assertJson([
            'date'            => '2026-06-15',
            'total_calories'  => 0,
            'total_protein_g' => 0,
            'total_carbs_g'   => 0,
            'total_fat_g'     => 0,
            'entry_count'     => 0,
        ]);
});

it('does not include another user\'s logs in the summary', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    MacroLog::factory()->for($userB)->create([
        'logged_at' => '2026-06-15',
        'protein_g' => 200,
        'carbs_g'   => 300,
        'fat_g'     => 100,
    ]);

    $this->actingAs($userA)->getJson('/api/daily-summary?date=2026-06-15')
        ->assertOk()
        ->assertJson([
            'total_protein_g' => 0,
            'entry_count'     => 0,
        ]);
});

it('returns exactly 2170 calories for 165g protein, 220g carbs, and 70g fat', function () {
    $user = User::factory()->create();

    MacroLog::factory()->for($user)->create([
        'logged_at' => '2026-06-15',
        'protein_g' => 165,
        'carbs_g'   => 220,
        'fat_g'     => 70,
    ]);

    $this->actingAs($user)->getJson('/api/daily-summary?date=2026-06-15')
        ->assertOk()
        ->assertJsonFragment(['total_calories' => 2170]);
});
