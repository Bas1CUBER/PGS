<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\UserPageAccess;
use App\Services\AuditLogService;
use App\Services\CacheInvalidationService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class UserController extends Controller
{
    private const ACCESS_MODULES = ['roadmaps', 'scorecard', 'performance_assessment', 'cascading', 'governance'];

    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $search = $request->string('search')->toString();
        $role = $request->string('role')->toString();
        $page = (int) $request->query('page', '1');

        // paginate() reads ?page= from the current request, so the page must
        // be part of the cache key or one page's result is served to all.
        $users = CacheInvalidationService::remember('user', "index:{$search}:{$role}:p{$page}", function () use ($search, $role): array {
            return User::query()
                ->with('pageAccess')
                ->when($search !== '', fn ($q) => $q->where(
                    fn ($q) => $q
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('office', 'like', '%'.$search.'%'),
                ))
                ->when(in_array($role, Role::values(), true), fn ($q) => $q->where('role', $role))
                ->orderBy('id')
                ->paginate(20)
                ->withQueryString()
                ->toArray();
        }, 60);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $role,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', [
            'roles' => Role::values(),
            'accessModules' => self::ACCESS_MODULES,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();

        $user = DB::transaction(function () use ($request, $validated): User {
            $created = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make((string) $validated['password']),
                'email_verified_at' => now(),
                'role' => $validated['role'],
                'office' => $validated['office'] ?? null,
                'is_active' => true,
            ]);

            UserPageAccess::query()->create(array_merge([
                'user_id' => $created->id,
            ], $this->accessMatrixFromRequest($request)));

            return $created;
        });

        $this->audit->record(
            $this->userOrFail($request)->id,
            'user.created',
            'user',
            (string) $user->id,
            after: ['email' => $user->email, 'role' => $user->role->value],
            request: $request,
        );

        CacheInvalidationService::onUserChange();

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Users/Edit', [
            'user' => $user->load('pageAccess'),
            'roles' => Role::values(),
            'accessModules' => self::ACCESS_MODULES,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validated();

        // Defense-in-depth: even if the admin-only route middleware is ever
        // relaxed, an account must never be able to elevate its own role,
        // deactivate itself, or sidestep the profile page's
        // current-password check by resetting its own password here.
        $actor = $this->userOrFail($request);
        $selfEdit = $actor->id === $user->id;
        abort_if($selfEdit && $validated['role'] !== $user->role->value, 403, 'You cannot change your own role.');
        abort_if($selfEdit && isset($validated['is_active']) && ! (bool) $validated['is_active'], 403, 'You cannot deactivate your own account.');
        abort_if($selfEdit && filled($validated['password'] ?? null), 403, 'Use the profile page to change your own password.');

        $before = ['email' => $user->email, 'role' => $user->role->value, 'is_active' => $user->is_active];

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'office' => $validated['office'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? $user->is_active),
        ]);

        if (($validated['password'] ?? null) !== null && $validated['password'] !== '') {
            $user->password = Hash::make((string) $validated['password']);
        }

        $user->save();

        $this->audit->record(
            $actor->id,
            'user.updated',
            'user',
            (string) $user->id,
            before: $before,
            after: ['email' => $user->email, 'role' => $user->role->value, 'is_active' => $user->is_active],
            request: $request,
        );

        CacheInvalidationService::onUserChange();

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function updateAccess(Request $request, User $user): RedirectResponse
    {
        $this->authorize('updateAccess', $user);

        $matrix = $this->accessMatrixFromRequest($request);

        UserPageAccess::query()->updateOrCreate(
            ['user_id' => $user->id],
            $matrix,
        );

        Cache::forget("pgs_access_{$user->id}");

        $this->audit->record(
            $this->userOrFail($request)->id,
            'user.access_updated',
            'user',
            (string) $user->id,
            after: $matrix,
            request: $request,
        );

        CacheInvalidationService::onUserChange();

        return redirect()->route('users.edit', $user)->with('success', 'Page access updated.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->authorize('toggleActive', $user);

        $user->update(['is_active' => ! $user->is_active]);

        $this->audit->record(
            $this->userOrFail($request)->id,
            'user.toggled',
            'user',
            (string) $user->id,
            after: ['is_active' => $user->is_active],
            request: $request,
        );

        CacheInvalidationService::onUserChange();

        return redirect()->route('users.index')->with('success', 'User '.($user->is_active ? 'activated' : 'deactivated').'.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $email = $user->email;
        $user->delete();

        Cache::forget("pgs_access_{$user->id}");

        $this->audit->record(
            $this->userOrFail($request)->id,
            'user.deleted',
            'user',
            null,
            before: ['email' => $email],
            request: $request,
        );

        CacheInvalidationService::onUserChange();

        return redirect()->route('users.index')->with('success', 'User deleted.');
    }

    public function import(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('import', User::class);

        $dryRun = $request->boolean('dry_run');

        Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ])->validate();

        $rows = $this->parseCsv($request->file('file')->getPathname());

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => ['The CSV file has no data rows.'],
            ]);
        }

        $report = [
            'total' => count($rows),
            'created' => 0,
            'errors' => [],
        ];

        $emails = array_column($rows, 'email');
        $existingEmails = User::query()->whereIn('email', $emails)->pluck('email')->flip()->all();

        // Emails duplicated WITHIN the file must be caught here too: both
        // rows would otherwise pass validation and the second insert would
        // crash on the unique index mid-import.
        $seenInFile = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2; // +1 for header, +1 for zero-based
            $error = $this->validateImportRow($row, $existingEmails);

            if ($error === null) {
                $emailKey = strtolower($row['email']);
                if (isset($seenInFile[$emailKey])) {
                    $error = 'Duplicate email within the file';
                } else {
                    $seenInFile[$emailKey] = true;
                }
            }

            if ($error !== null) {
                $report['errors'][] = ['line' => $line, 'message' => $error];

                continue;
            }

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($row): void {
                $user = User::query()->create([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'password' => Hash::make($row['password']),
                    'email_verified_at' => now(),
                    'role' => $row['role'],
                    'office' => $row['office'] !== '' ? $row['office'] : null,
                    'is_active' => true,
                ]);

                UserPageAccess::query()->create(['user_id' => $user->id] + array_fill_keys(self::ACCESS_MODULES, true));
            });

            $report['created']++;
        }

        $this->audit->record(
            $this->userOrFail($request)->id,
            'users.imported',
            'user',
            null,
            after: ['total' => $report['total'], 'created' => $report['created'], 'errors' => count($report['errors'])],
            request: $request,
        );

        if ($report['created'] > 0) {
            CacheInvalidationService::onUserChange();
        }

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        return back()->with('success', "Imported {$report['created']} of {$report['total']} users.");
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $existingEmails
     */
    private function validateImportRow(array $row, array $existingEmails = []): ?string
    {
        foreach (['email', 'password', 'role', 'name'] as $required) {
            if (trim($row[$required] ?? '') === '') {
                return "Missing required column: {$required}";
            }
        }

        if (filter_var($row['email'], FILTER_VALIDATE_EMAIL) === false) {
            return 'Invalid email format';
        }

        if (isset($existingEmails[$row['email']])) {
            return 'Email already exists';
        }

        if (! in_array($row['role'], Role::values(), true)) {
            return 'Invalid role: '.$row['role'];
        }

        if (strlen($row['password']) < 12) {
            return 'Password must be at least 12 characters';
        }

        if (preg_match('/[A-Z]/', $row['password']) === 0
            || preg_match('/[a-z]/', $row['password']) === 0
            || preg_match('/[0-9]/', $row['password']) === 0
            || preg_match('/[^A-Za-z0-9]/', $row['password']) === 0) {
            return 'Password must contain uppercase, lowercase, numbers, and symbols';
        }

        return null;
    }

    /**
     * @return list<array{email: string, password: string, role: string, name: string, office: string}>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => ['Could not read the CSV file.']]);
        }

        $rows = [];
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = array_map(static fn (mixed $h): string => strtolower(trim((string) $h)), $header);

        while (($data = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($header as $i => $column) {
                $row[$column] = trim($data[$i] ?? '');
            }
            $rows[] = [
                'email' => $row['email'] ?? '',
                'password' => $row['password'] ?? '',
                'role' => $row['role'] ?? '',
                'name' => $row['name'] ?? '',
                'office' => $row['office'] ?? '',
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<string, bool>
     */
    private function accessMatrixFromRequest(Request $request): array
    {
        $matrix = [];

        foreach (self::ACCESS_MODULES as $module) {
            $matrix[$module] = $request->boolean($module);
        }

        return $matrix;
    }

    /**
     * @throws AuthenticationException
     */
    private function userOrFail(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
