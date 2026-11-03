<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VipMember;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VipController extends Controller
{
    public function index(): View
    {
        $members = VipMember::query()->orderByRaw('expires_at IS NULL DESC')->orderByDesc('expires_at')->get();

        return view('admin.vip.index', [
            'members' => $members,
            'activeCount' => $members->filter->isActive()->count(),
            'revenue' => (int) $members->sum('amount'),
        ]);
    }

    public function store(Request $request, AdminAuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'steam_id' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:80'],
            'plan' => ['required', 'string', 'max:40'],
            'amount' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'starts_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['amount'] = (int) ($data['amount'] ?? 0);
        $member = VipMember::create($data);
        $audit->record($request, 'vip.created', null, ['steam_id' => $member->steam_id], $member->name);

        return back()->with('status', 'VIP тоглогч нэмэгдлээ.');
    }

    public function destroy(Request $request, VipMember $member, AdminAuditLogger $audit): RedirectResponse
    {
        $audit->record($request, 'vip.deleted', null, ['steam_id' => $member->steam_id], $member->name);
        $member->delete();

        return back()->with('status', 'VIP бүртгэл устгагдлаа.');
    }
}
