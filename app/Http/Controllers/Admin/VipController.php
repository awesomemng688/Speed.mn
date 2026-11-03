<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VipMember;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VipController extends Controller
{
    private function filtered(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $escaped = addcslashes(mb_substr($search, 0, 100), '%_\\');

        return VipMember::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$escaped}%")
                ->orWhere('steam_id', 'like', "%{$escaped}%")
                ->orWhere('plan', 'like', "%{$escaped}%")))
            ->orderByRaw('expires_at IS NULL DESC')
            ->orderByDesc('expires_at');
    }

    public function index(Request $request): View
    {
        $members = $this->filtered($request)->get();

        return view('admin.vip.index', [
            'members' => $members,
            'activeCount' => $members->filter->isActive()->count(),
            'revenue' => (int) $members->sum('amount'),
            'search' => trim((string) $request->query('search', '')),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $members = $this->filtered($request)->get();

        return response()->streamDownload(function () use ($members): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Нэр', 'Steam ID', 'Багц', 'Дүн', 'Эхлэх', 'Дуусах', 'Тэмдэглэл']);
            foreach ($members as $m) {
                $cells = [$m->name, $m->steam_id, $m->plan, $m->amount, $m->starts_at?->format('Y-m-d'), $m->expires_at?->format('Y-m-d'), $m->note];
                // Neutralise spreadsheet formula injection.
                $cells = array_map(fn ($c) => is_string($c) && preg_match('/^[=+\-@\t\r]/', $c) ? "'".$c : $c, $cells);
                fputcsv($out, $cells);
            }
            fclose($out);
        }, 'vip-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
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
