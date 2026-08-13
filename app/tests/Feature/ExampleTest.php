<?php

declare(strict_types=1);

test('the application returns a successful response', function (): void {
    $this->get('/')->assertStatus(200);
});

test('the health endpoint verifies the database connection', function (): void {
    $this->get('/up')
        ->assertStatus(200)
        ->assertJsonPath('status', 'up');
});
