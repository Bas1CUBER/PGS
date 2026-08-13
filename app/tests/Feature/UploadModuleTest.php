<?php

declare(strict_types=1);

use App\Models\DeadlineControl;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('lists the upload modules', function (): void {
    $user = User::factory()->employee()->create();

    $this->actingAs($user)
        ->get('/uploads')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Uploads/Index')
            ->has('modules', 8));
});

it('lists resources with uploader info', function (): void {
    $user = User::factory()->employee()->create();
    DB::table('resources_uploads')->insert([
        'title' => 'Policy PDF',
        'filename' => 'uploads/resources/policy.pdf',
        'original_name' => 'policy.pdf',
        'file_size' => 100,
        'mime_type' => 'application/pdf',
        'uploaded_by' => $user->id,
        'uploaded_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/uploads/resources')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Uploads/Show')
            ->has('rows', 1)
            ->where('rows.0.original_name', 'policy.pdf'));
});

it('uploads a file to a module', function (): void {
    Storage::fake('local');
    $user = User::factory()->employee()->create();

    $this->actingAs($user)
        ->post('/uploads/resources', [
            'title' => 'New guide',
            'file' => UploadedFile::fake()->create('guide.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    $row = DB::table('resources_uploads')->first();
    expect($row)->not->toBeNull()
        ->and($row->title)->toBe('New guide')
        ->and($row->uploaded_by)->toBe($user->id);
});

it('approves and returns pending uploads as focal', function (): void {
    $focal = User::factory()->focal()->create();
    $employee = User::factory()->employee()->create();
    $id = DB::table('operations_review_uploads')->insertGetId([
        'employee_id' => $employee->id,
        'filename' => 'x.pdf',
        'original_name' => 'x.pdf',
        'file_size' => 10,
        'mime_type' => 'application/pdf',
        'status' => 'Pending',
        'uploaded_at' => now(),
    ]);

    $this->actingAs($focal)
        ->put("/uploads/operations-review/{$id}/status", ['status' => 'Approved'])
        ->assertRedirect();

    expect(DB::table('operations_review_uploads')->where('id', $id)->value('status'))->toBe('Approved');
});

it('blocks uploads when the deadline has passed', function (): void {
    DeadlineControl::query()->create([
        'role' => 'employee',
        'enabled' => true,
        'end_time' => now()->subHour(),
        'message' => 'Deadline passed.',
    ]);
    $user = User::factory()->employee()->create();

    $this->actingAs($user)
        ->post('/uploads/resources', [
            'title' => 'Late',
            'file' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('deadline');
});

it('rejects unknown upload module slugs', function (): void {
    $user = User::factory()->employee()->create();

    $this->actingAs($user)->get('/uploads/nope')->assertNotFound();
});
