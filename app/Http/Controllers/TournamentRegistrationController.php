<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\TournamentRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TournamentRegistrationController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
    {
        abort_unless(
            $post->type === 'tournament'
            && $post->is_published
            && ($post->published_at === null || $post->published_at->isPast()),
            404,
        );
        abort_if($post->event_at && $post->event_at->isPast(), 422, 'Бүртгэл хаагдсан байна.');

        $data = $request->validate([
            'participant_name' => ['required', 'string', 'max:80'],
            'team_name' => ['nullable', 'string', 'max:80'],
            'contact' => ['nullable', 'string', 'max:100'],
        ]);
        TournamentRegistration::query()->updateOrCreate(
            ['post_id' => $post->id, 'user_id' => $request->user()->id],
            $data + ['status' => 'registered'],
        );

        return back()->with('status', 'Тэмцээнд амжилттай бүртгүүллээ.');
    }
}
