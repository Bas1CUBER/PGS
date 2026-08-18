<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\RunBackupJob;
use App\Services\AuditLogService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Backup\BackupDestination\BackupDestination;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BackupController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function index(): Response
    {
        abort_unless(($user = auth()->user()) !== null && $user->isAdmin(), 403);

        $backups = [];

        foreach (config('backup.backup.destination.disks', []) as $disk) {
            $destination = BackupDestination::create($disk, (string) config('backup.backup.name', 'pgs'));

            foreach ($destination->backups() as $backup) {
                $backups[] = [
                    'disk' => $disk,
                    'path' => $backup->path(),
                    'size' => (int) $backup->sizeInBytes(),
                    'date' => $backup->date()->getTimestamp(),
                ];
            }
        }

        usort($backups, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return Inertia::render('Backups/Index', [
            'backups' => array_slice($backups, 0, 50),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->isAdmin(), 403);

        if (app()->isProduction() && blank(config('backup.backup.password'))) {
            return back()->with('error', 'Backup encryption is not configured. Set BACKUP_ARCHIVE_PASSWORD before creating a production backup.');
        }

        RunBackupJob::dispatch();

        $this->audit->record(
            $this->userId($request),
            'backup.created',
            'backup',
            null,
            request: $request,
        );

        return back()->with('success', 'Backup started. It will complete in the background.');
    }

    public function download(Request $request, string $disk, string $path): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->isAdmin(), 403);

        if (! in_array($disk, config('backup.backup.destination.disks', []), true)) {
            abort(404);
        }

        if (! $this->isKnownBackupPath($disk, $path)) {
            abort(404);
        }

        $this->audit->record(
            $this->userId($request),
            'backup.downloaded',
            'backup',
            $path,
            request: $request,
        );

        return Storage::disk($disk)->download($path);
    }

    public function destroy(Request $request, string $disk, string $path): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->isAdmin(), 403);

        if (! in_array($disk, config('backup.backup.destination.disks', []), true)) {
            abort(404);
        }

        if (! $this->isKnownBackupPath($disk, $path)) {
            abort(404);
        }

        Storage::disk($disk)->delete($path);

        $this->audit->record(
            $this->userId($request),
            'backup.deleted',
            'backup',
            $path,
            request: $request,
        );

        return back()->with('success', 'Backup deleted.');
    }

    public function restore(Request $request, string $disk, string $path): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->isAdmin(), 403);

        if (! in_array($disk, config('backup.backup.destination.disks', []), true)) {
            abort(404);
        }

        if (! $this->isKnownBackupPath($disk, $path)) {
            abort(404);
        }

        try {
            Artisan::call('backup:restore', [
                '--backup' => $path,
                '--disk' => $disk,
            ]);

            $this->audit->record(
                $this->userId($request),
                'backup.restored',
                'backup',
                $path,
                request: $request,
            );

            return back()->with('success', 'Backup restored successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Restore failed: '.$e->getMessage());
        }
    }

    /**
     * @throws AuthenticationException
     */
    private function userId(Request $request): int
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        return $user->id;
    }

    private function isKnownBackupPath(string $disk, string $path): bool
    {
        if (! in_array($disk, config('backup.backup.destination.disks', []), true)) {
            return false;
        }

        $destination = BackupDestination::create($disk, (string) config('backup.backup.name', 'pgs'));

        foreach ($destination->backups() as $backup) {
            if ($backup->path() === $path) {
                return true;
            }
        }

        return false;
    }
}
