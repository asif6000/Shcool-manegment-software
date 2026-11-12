<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('audit.view'), 403);

        $base = AuditLog::query()->forActor($request->user());

        $events = (clone $base)
            ->select('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event');

        $query = (clone $base)->with('user');

        if ($request->filled('event')) {
            $query->where('event', (string) $request->input('event'));
        }

        if ($request->filled('search')) {
            $query->where('description', 'like', '%'.trim((string) $request->input('search')).'%');
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', (string) $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', (string) $request->input('to'));
        }

        return view('audit.index', [
            'logs' => $query->latest('created_at')->paginate(20)->withQueryString(),
            'events' => $events,
            'filters' => $request->only(['event', 'search', 'from', 'to']),
        ]);
    }
}
