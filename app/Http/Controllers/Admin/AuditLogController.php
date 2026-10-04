<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:100'],
        ]);

        $logs = AdminAuditLog::query()
            ->with('actor')
            ->when($filters['search'] ?? null, function ($query, string $term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('subject_label', 'like', "%{$term}%")
                        ->orWhere('event', 'like', "%{$term}%")
                        ->orWhereHas('actor', fn ($actor) => $actor->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($filters['event'] ?? null, fn ($query, string $event) => $query->where('event', $event))
            ->latest()
            ->paginate(30)
            ->withQueryString();
        $events = AdminAuditLog::query()->distinct()->orderBy('event')->pluck('event');

        return view('admin.audit.index', [
            'logs' => $logs,
            'events' => $events,
            'search' => $filters['search'] ?? '',
            'event' => $filters['event'] ?? '',
        ]);
    }
}