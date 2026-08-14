<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('stores password reset codes in the outbox when the outbox mailer is selected', function (): void {
    config()->set('mail.default', 'outbox');

    $user = User::factory()->employee()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('outbox_mails', [
        'to_email' => $user->email,
    ]);

    $mail = DB::table('outbox_mails')->where('to_email', $user->email)->first();

    expect($mail)->not->toBeNull()
        ->and($mail->subject)->toContain('password reset code')
        ->and($mail->body)->toContain('reset code');
});

it('lists outbox mail for admins only', function (): void {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->employee()->create();

    DB::table('outbox_mails')->insert([
        'to_email' => 'person@trcdoh.ph',
        'subject' => 'Password Reset',
        'body' => '<p>hi</p>',
        'created_at' => now(),
    ]);

    $this->actingAs($employee)->get('/mailbox')->assertForbidden();
    $this->actingAs($admin)->get('/mailbox')->assertOk()->assertSee('Password Reset');

    $id = DB::table('outbox_mails')->first()->id;

    $this->actingAs($admin)->get("/mailbox/{$id}")->assertOk();
});
