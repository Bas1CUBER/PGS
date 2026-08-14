<?php

it('redirects the public root to login', function (): void {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
