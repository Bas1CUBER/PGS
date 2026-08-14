<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class MailboxController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();

        $mails = DB::table('outbox_mails')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where('to_email', 'like', '%'.$search.'%')
                    ->orWhere('subject', 'like', '%'.$search.'%');
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Mailbox/Index', [
            'mails' => $mails,
            'filters' => ['q' => $search],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $mail = DB::table('outbox_mails')->where('id', $id)->first();

        if ($mail === null) {
            abort(404);
        }

        return Inertia::render('Mailbox/Show', [
            'mail' => (array) $mail,
        ]);
    }
}
