<?php

declare(strict_types=1);

use App\Jobs\RunBackupJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('lets an admin start a backup', function (): void {
    Queue::fake();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post('/backups')->assertRedirect();

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
    $confirmed = ['auth.password_confirmed_at' => time()];

    $user = User::factory()->employee()->create();
    $this->actingAs($user)
        ->withSession($confirmed)
        ->post('/backups/local/not-a-real-backup.zip/restore')
        ->assertForbidden();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)
        ->withSession($confirmed)
        ->post('/backups/local/not-a-real-backup.zip/restore')
        ->assertNotFound();
});

it('requires fresh password confirmation for backup restore', function (): void {
    // No auth.password_confirmed_at session: even an admin is bounced to
    // the confirmation screen before the destructive action runs.
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)
        ->post('/backups/local/some-backup.zip/restore')
        ->assertRedirect();
});
