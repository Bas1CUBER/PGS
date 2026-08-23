<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\File;

/**
 * Run a callback that exercises StaticContentController's file-backed writes,
 * then restore the original data file so the repo is never mutated by tests.
 */
function withStructuredFile(string $type, callable $callback): void
{
    $file = $type === 'charter' ? 'charter_statements.json' : 'user_access_matrix.json';
    $path = base_path('../data/'.$file);

    $original = is_file($path) ? file_get_contents($path) : null;

    try {
        $callback();
    } finally {
        if ($original === null) {
            @unlink($path);
        } else {
            File::put($path, $original);
        }
    }
}

it('lets an admin save charter statements', function (): void {
    $admin = User::factory()->admin()->create();

    withStructuredFile('charter', function () use ($admin): void {
        $this->actingAs($admin)
            ->post('/content/about-charter-statements/structured', [
                'vision' => 'Our vision',
                'mission' => 'Our mission',
                'core_values' => "Compassion\nRectitude\nTeamwork",
            ])
            ->assertRedirect();

        $data = json_decode((string) file_get_contents(base_path('../data/charter_statements.json')), true);
        expect($data['vision'])->toBe('Our vision');
        expect($data['core_values'])->toBe(['Compassion', 'Rectitude', 'Teamwork']);
    });
});

it('lets an admin save the user access matrix', function (): void {
    $admin = User::factory()->admin()->create();

    withStructuredFile('access', function () use ($admin): void {
        $matrix = ['columns' => ['Page', 'Admin'], 'rows' => [['Page' => 'Roadmaps', 'Admin' => 'All']]];

        $this->actingAs($admin)
            ->post('/content/about-user-access/structured', ['matrix' => json_encode($matrix)])
            ->assertRedirect();

        $data = json_decode((string) file_get_contents(base_path('../data/user_access_matrix.json')), true);
        expect($data['columns'])->toBe(['Page', 'Admin']);
    });
});

it('forbids employees from saving structured content', function (): void {
    $user = User::factory()->employee()->create();

    $this->actingAs($user)
        ->post('/content/about-charter-statements/structured', [
            'vision' => 'V',
            'mission' => 'M',
            'core_values' => 'A',
        ])
        ->assertForbidden();
});

it('guards image replacement behind admin and validation', function (): void {
    $user = User::factory()->employee()->create();
    $this->actingAs($user)->post('/content/about-strategy-map/image')->assertForbidden();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post('/content/about-strategy-map/image', [])->assertSessionHasErrors('image');
});
