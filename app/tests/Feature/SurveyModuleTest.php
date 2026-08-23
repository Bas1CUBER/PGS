<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lets an admin publish a survey', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/surveys', ['title' => 'Feedback Form', 'url' => 'https://example.com/feedback'])
        ->assertRedirect();

    expect(DB::table('surveys')->where('title', 'Feedback Form')->exists())->toBeTrue();
});

it('forbids non-admins from publishing surveys', function (): void {
    $user = User::factory()->employee()->create();

    $this->actingAs($user)
        ->post('/surveys', ['title' => 'X', 'url' => 'https://example.com/x'])
        ->assertForbidden();

    expect(DB::table('surveys')->where('title', 'X')->exists())->toBeFalse();
});

it('validates survey input', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/surveys', ['title' => '', 'url' => 'not-a-url'])
        ->assertSessionHasErrors(['title', 'url']);
});

it('lets an admin update, archive and delete a survey', function (): void {
    $admin = User::factory()->admin()->create();
    $id = DB::table('surveys')->insertGetId([
        'title' => 'Old Survey',
        'url' => 'https://example.com/old',
        'status' => 'Active',
        'created_by' => $admin->id,
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->put("/surveys/{$id}", ['title' => 'New Survey', 'url' => 'https://example.com/new'])
        ->assertRedirect();

    expect(DB::table('surveys')->where('id', $id)->value('title'))->toBe('New Survey');

    $this->actingAs($admin)->post("/surveys/{$id}/archive")->assertRedirect();
    expect(DB::table('surveys')->where('id', $id)->value('archived_at'))->not->toBeNull();

    $this->actingAs($admin)->delete("/surveys/{$id}")->assertRedirect();
    expect(DB::table('surveys')->where('id', $id)->exists())->toBeFalse();
});

it('cannot delete an unarchived survey', function (): void {
    $admin = User::factory()->admin()->create();
    $id = DB::table('surveys')->insertGetId([
        'title' => 'Live Survey',
        'url' => 'https://example.com/live',
        'status' => 'Active',
        'created_by' => $admin->id,
        'created_at' => now(),
    ]);

    $this->actingAs($admin)->delete("/surveys/{$id}")->assertNotFound();
    expect(DB::table('surveys')->where('id', $id)->exists())->toBeTrue();
});
