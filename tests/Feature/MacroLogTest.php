<?php

use App\Models\MacroLog;
use App\Models\User;

it('allows an authenticated user to create a macro log', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/macro-logs', [
        'logged_at'   => '2026-06-15',
        'protein_g'   => 30,
        'carbs_g'     => 50,
        'fat_g'       => 10,
        'description' => 'Breakfast',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('macro_logs', [
        'user_id'   => $user->id,
        'logged_at' => '2026-06-15',
    ]);
});

it('allows an authenticated user to update their own macro log', function () {
    $user = User::factory()->create();
    $log  = MacroLog::factory()->for($user)->create([
        'logged_at' => '2026-06-15',
        'protein_g' => 30,
        'carbs_g'   => 50,
        'fat_g'     => 10,
    ]);

    $this->actingAs($user)->putJson("/api/macro-logs/{$log->id}", [
        'logged_at' => '2026-06-15',
        'protein_g' => 40,
        'carbs_g'   => 60,
        'fat_g'     => 15,
    ])->assertOk();

    $this->assertDatabaseHas('macro_logs', [
        'id'        => $log->id,
        'protein_g' => 40,
    ]);
});

it('allows an authenticated user to delete their own macro log', function () {
    $user = User::factory()->create();
    $log  = MacroLog::factory()->for($user)->create();

    $this->actingAs($user)->deleteJson("/api/macro-logs/{$log->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('macro_logs', ['id' => $log->id]);
});

it('prevents a user from updating another user\'s macro log', function () {
    $owner    = User::factory()->create();
    $attacker = User::factory()->create();
    $log      = MacroLog::factory()->for($owner)->create();

    $this->actingAs($attacker)->putJson("/api/macro-logs/{$log->id}", [
        'logged_at' => '2026-06-15',
        'protein_g' => 40,
        'carbs_g'   => 60,
        'fat_g'     => 15,
    ])->assertNotFound();
});

it('prevents a user from deleting another user\'s macro log', function () {
    $owner    = User::factory()->create();
    $attacker = User::factory()->create();
    $log      = MacroLog::factory()->for($owner)->create();

    $this->actingAs($attacker)->deleteJson("/api/macro-logs/{$log->id}")
        ->assertNotFound();
});
