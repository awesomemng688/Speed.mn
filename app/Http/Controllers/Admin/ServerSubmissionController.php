<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PollServer;
use App\Models\Server;
use App\Models\ServerSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServerSubmissionController extends Controller
{
    public function index(): View
    {
        return view('admin.submissions.index', [
            'pending' => ServerSubmission::with('user')->where('status', 'pending')->oldest()->get(),
            'reviewed' => ServerSubmission::with('user')->where('status', '!=', 'pending')->latest('reviewed_at')->limit(20)->get(),
        ]);
    }

    public function approve(ServerSubmission $submission): RedirectResponse
    {
        if ($submission->status !== 'pending') {
            return back();
        }

        $server = Server::firstOrCreate(
            ['ip' => $submission->ip, 'port' => $submission->port],
            [
                'name' => $submission->name,
                'game' => $submission->game,
                'category' => $submission->category,
                'region' => $submission->region,
                'max_players' => $submission->max_players,
                'query_type' => 'a2s',
                'enabled' => true,
            ],
        );
        $submission->update(['status' => 'approved', 'reviewed_at' => now()]);
        PollServer::dispatch($server->id);

        return back()->with('status', "{$server->name} батлагдлаа.");
    }

    public function reject(ServerSubmission $submission): RedirectResponse
    {
        if ($submission->status === 'pending') {
            $submission->update(['status' => 'rejected', 'reviewed_at' => now()]);
        }

        return back()->with('status', 'Хүсэлт татгалзагдлаа.');
    }
}
