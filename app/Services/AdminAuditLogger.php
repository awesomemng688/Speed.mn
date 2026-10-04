<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AdminAuditLogger
{
    public function record(
        Request $request,
        string $event,
        ?Model $subject = null,
        array $details = [],
        ?string $subjectLabel = null,
    ): void {
        AdminAuditLog::create([
            'actor_user_id' => $request->user()?->getKey(),
            'event' => $event,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel ?? ($subject?->name ?? null),
            'details' => $details,
            'ip_address' => $request->ip(),
        ]);
    }
}