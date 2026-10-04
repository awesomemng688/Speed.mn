<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PollServer;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class FailedJobController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $jobs = DB::table('failed_jobs')
            ->when($filters['search'] ?? null, function ($query, string $term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('queue', 'like', "%{$term}%")
                        ->orWhere('payload', 'like', "%{$term}%")
                        ->orWhere('exception', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('failed_at')
            ->paginate(25)
            ->withQueryString()
            ->through(function (object $job): object {
                $payload = json_decode($job->payload, true) ?: [];
                $job->job_name = $payload['displayName'] ?? $payload['data']['commandName'] ?? 'Unknown job';
                $job->job_class = $payload['data']['commandName'] ?? null;
                $job->exception_summary = Str::limit(Str::before($job->exception, "\n"), 240);

                return $job;
            });

        return view('admin.failed-jobs.index', [
            'jobs' => $jobs,
            'search' => $filters['search'] ?? '',
        ]);
    }

    public function retry(Request $request, string $uuid, AdminAuditLogger $audit): RedirectResponse
    {
        $job = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        abort_unless($job, 404);
        $payload = json_decode($job->payload, true) ?: [];
        if (($payload['data']['commandName'] ?? null) !== PollServer::class) {
            return back()->with('error', 'Only server-poll jobs can be retried here.');
        }

        try {
            $exitCode = Artisan::call('queue:retry', ['id' => [$uuid]]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'The failed job could not be queued for retry.');
        }

        if ($exitCode !== 0) {
            return back()->with('error', 'The failed job could not be queued for retry.');
        }

        $audit->record($request, 'queue.failed_job_retried', null, [
            'uuid' => $uuid,
            'queue' => $job->queue,
        ], $this->jobName($job->payload));

        return redirect()->route('admin.failed-jobs.index')->with('status', 'Failed job queued for retry.');
    }

    public function destroy(Request $request, string $uuid, AdminAuditLogger $audit): RedirectResponse
    {
        $job = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        abort_unless($job, 404);

        $exitCode = Artisan::call('queue:forget', ['id' => $uuid]);
        if ($exitCode !== 0) {
            return back()->with('error', 'The failed job could not be removed.');
        }

        $audit->record($request, 'queue.failed_job_forgotten', null, [
            'uuid' => $uuid,
            'queue' => $job->queue,
        ], $this->jobName($job->payload));

        return redirect()->route('admin.failed-jobs.index')->with('status', 'Failed job removed.');
    }

    private function jobName(string $payload): string
    {
        $data = json_decode($payload, true) ?: [];

        return $data['displayName'] ?? $data['data']['commandName'] ?? 'Unknown job';
    }
}