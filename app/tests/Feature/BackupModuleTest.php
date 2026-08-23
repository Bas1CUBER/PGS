<?php

declare(strict_types=1);

use App\Jobs\RunBackupJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('lets an admin start a backup', function (): void {
    Queue::fake();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/backups')->assertRedirect();

    Queue::assertPushed(RunBackupJob::class);
    expect(DB::table('audit_logs')->where('action', 'backup.created')->exists())->toBeTrue();
});

it('forbids non-admins from creating backups', function (): void {
    Queue::fake();
    $user = User::factory()->employee()->create();

    $this->actingAs($user)->post('/backups')->assertForbidden();

    Queue::assertNothingPushed();
});

it('restricts backup restore to admins and known paths', function (): void {
    $user = User::factory()->employee()->create();
    $this->actingAs($user)
        ->post('/backups/local/not-a-real-backup.zip/restore')
        ->assertForbidden();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)
        ->post('/backups/local/not-a-real-backup.zip/restore')
        ->assertNotFound();
});
