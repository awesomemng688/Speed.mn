<?php

namespace App\Http\Controllers;

use App\Models\ServerSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServerSubmissionController extends Controller
{
    public function create(Request $request): View
    {
        return view('submissions.create', [
            'submissions' => $request->user()->hasMany(ServerSubmission::class)->latest()->limit(10)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'game' => ['required', Rule::in(['cs2', 'cs16'])],
            'category' => ['nullable', 'required_if:game,cs16', Rule::in(['public-1', 'public-2', 'knife-1', 'knife-2'])],
            'ip' => ['required', 'string', 'max:45', function ($attribute, $value, $fail) {
                if (! filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $fail('Нийтийн IP хаяг оруулна уу.');
                }
            }],
            'port' => ['required', 'integer', 'between:1,65535'],
            'region' => ['nullable', 'string', 'max:100'],
            'max_players' => ['required', 'integer', 'between:1,128'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if (ServerSubmission::where('user_id', $user->id)->where('status', 'pending')->count() >= 3) {
            return back()->withInput()->withErrors(['name' => 'Хүлээгдэж буй 3-аас илүү хүсэлт илгээх боломжгүй.']);
        }

        if (\App\Models\Server::where('ip', $data['ip'])->where('port', $data['port'])->exists()) {
            return back()->withInput()->withErrors(['ip' => 'Энэ сервер аль хэдийн бүртгэлтэй байна.']);
        }

        if ($data['game'] !== 'cs16') {
            $data['category'] = null;
        }

        ServerSubmission::create($data + ['user_id' => $user->id]);

        return redirect()->route('submissions.create')->with('status', 'Хүсэлт илгээгдлээ. Админ баталсны дараа жагсаалтад орно.');
    }
}
