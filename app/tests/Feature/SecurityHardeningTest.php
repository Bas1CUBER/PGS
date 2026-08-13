<?php

declare(strict_types=1);

use App\Enums\DeliverableStatus;
use App\Models\User;

it('sends the security headers on web responses', function (): void {
    $user = User::factory()->employee()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy')
        ->assertHeader('Content-Security-Policy-Report-Only');
});

it('declares auth and throttle middleware on state-changing routes', function (): void {
    $expectations = [
        'deliverables.store' => ['auth', 'throttle:submissions'],
        'deliverables.transition' => ['auth'],
        'notices.store' => ['auth'],
        'sectors.rows.update' => ['auth'],
        'users.store' => ['auth', 'role:admin'],
        'deadlines.update' => ['auth', 'role:admin'],
        'backups.create' => ['auth', 'role:admin'],
        'roadmaps.index' => ['auth', 'page.access:roadmaps'],
    ];

    foreach ($expectations as $name => $expected) {
        $middleware = Route::getRoutes()->getByName($name)->middleware();

        foreach ($expected as $item) {
            expect($middleware)->toContain($item);
        }
    }
});

it('throttles rapid deliverable submissions', function (): void {
    $user = User::factory()->employee()->create();

    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($user)->post('/deliverables', [
            'title' => "Bulk $i",
            'status' => DeliverableStatus::NotYetStarted->value,
        ]);
    }

    $this->actingAs($user)
        ->post('/deliverables', [
            'title' => 'Too many',
            'status' => DeliverableStatus::NotYetStarted->value,
        ])
        ->assertTooManyRequests();
});

it('blocks guests from authenticated module routes', function (): void {
    $this->get('/deliverables')->assertRedirect('/login');
    $this->get('/roadmaps')->assertRedirect('/login');
    $this->get('/notices')->assertRedirect('/login');
    $this->get('/sectors')->assertRedirect('/login');
});

it('redirects legacy URLs to their new routes', function (): void {
    $this->get('/PGS/employee_dashboard.php')->assertRedirect('/dashboard');
    $this->get('/PGS/login.php')->assertRedirect('/login');
    $this->get('/user_management.php')->assertRedirect('/users');
    $this->get('/roadmap.php')->assertRedirect('/roadmaps');
    $this->get('/PGS/')->assertRedirect('/dashboard');
});
