<?php

use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('forgot password requests a six digit reset code', function (): void {
    Mail::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('password.code'));

    $record = PasswordResetCode::query()->where('email', $user->email)->firstOrFail();
    $mail = Mail::sent(PasswordResetCodeMail::class)->first();
    $code = (string) $mail?->code;

    expect($code)->toMatch('/^\d{6}$/')
        ->and(Hash::check($code, $record->code_hash))->toBeTrue()
        ->and($record->code_hash)->not->toBe($code);
});

test('forgot password does not reveal whether an email exists', function (): void {
    Mail::fake();

    $response = $this->post('/forgot-password', ['email' => 'missing@example.com']);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('password.code'))
        ->assertSessionHas('password_reset_email', 'missing@example.com');

    Mail::assertNothingSent();
});

test('a valid reset code allows the user to change the password', function (): void {
    Mail::fake();

    $user = User::factory()->create(['password' => 'OldPassword-123']);

    $this->post('/forgot-password', ['email' => $user->email]);

    $mail = Mail::sent(PasswordResetCodeMail::class)->first();
    $code = (string) $mail?->code;

    $this->post('/reset-password/code', ['code' => $code])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('password.change'));

    $this->post('/reset-password/change', [
        'password' => 'NewPassword-123',
        'password_confirmation' => 'NewPassword-123',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('NewPassword-123', $user->fresh()->password))->toBeTrue();
    $this->assertDatabaseHas('password_reset_codes', [
        'email' => $user->email,
    ]);
    expect(PasswordResetCode::query()->where('email', $user->email)->firstOrFail()->used_at)->not->toBeNull();
});

test('an invalid reset code is rejected and counted', function (): void {
    Mail::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    $this->post('/reset-password/code', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect(PasswordResetCode::query()->where('email', $user->email)->firstOrFail()->attempts)->toBe(1);
});

test('an expired reset code cannot be used', function (): void {
    Mail::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);
    PasswordResetCode::query()->where('email', $user->email)->update(['expires_at' => now()->subMinute()]);

    $this->post('/reset-password/code', ['code' => '123456'])
        ->assertSessionHasErrors('code');
});
