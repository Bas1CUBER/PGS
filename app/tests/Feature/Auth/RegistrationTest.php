<?php

test('public registration is disabled for the internal application', function () {
    $this->get('/register')->assertNotFound();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password-12345',
        'password_confirmation' => 'password-12345',
    ]);

    $this->assertGuest();
    $response->assertStatus(405);
});
