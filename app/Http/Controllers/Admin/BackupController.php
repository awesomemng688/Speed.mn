<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    private const NAME_PATTERN = '/^speedmn-\d{8}-\d{6}-[0-9a-f]{6}\.sql\.gz$/';

    public function index(): View
    {
        $backups = collect(glob($this->directory().'/speedmn-*.sql.gz') ?: [])
            ->filter(fn (string $path) => preg_match(self::NAME_PATTERN, basename($path)) === 1)
            ->map(fn (string $path) => [
                'name' => basename($path),
                'size' => filesize($path) ?: 0,
                'at' => \Illuminate\Support\Carbon::createFromTimestamp(filemtime($path)),
            ])
            ->sortByDesc('at')
            ->values();

        $lastSuccess = Cache::get('speedmn.backup.last_success_at');

        return view('admin.backups.index', [
            'backups' => $backups,
            'lastSuccessAt' => $lastSuccess ? \Illuminate\Support\Carbon::parse($lastSuccess) : null,
        ]);
    }

    public function run(Request $request, AdminAuditLogger $audit): RedirectResponse
    {
        @set_time_limit(300);
        $exit = Artisan::call('speedmn:backup');
        $audit->record($request, 'backup.run', null, ['exit' => $exit]);

        return $exit === 0
            ? back()->with('status', 'Нөөцлөлт амжилттай хийгдлээ.')
            : back()->with('error', 'Нөөцлөлт амжилтгүй боллоо. Серверийн лог шалгана уу.');
    }

    public function download(Request $request, string $name, AdminAuditLogger $audit): BinaryFileResponse
    {
        abort_unless(preg_match(self::NAME_PATTERN, $name) === 1, 404);
        $path = $this->directory().'/'.$name;
        abort_unless(is_file($path), 404);

        $audit->record($request, 'backup.downloaded', null, [], $name);

        return response()->download($path, $name, ['Content-Type' => 'application/gzip']);
    }

    private function directory(): string
    {
        return storage_path('app/private/backups');
    }
}
