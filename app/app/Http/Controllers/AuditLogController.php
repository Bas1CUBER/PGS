<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->string('action')->toString() !== '', fn ($q) => $q->where('action', 'like', '%'.$request->string('action')->toString().'%'))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('AuditLogs/Index', [
            'logs' => $logs,
            'filters' => ['action' => $request->string('action')->toString()],
        ]);
    }
}
