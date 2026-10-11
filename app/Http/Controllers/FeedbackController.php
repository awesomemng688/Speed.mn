<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function create(): View
    {
        return view('feedback');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        Feedback::create([
            'user_id' => $request->user()?->id,
            'name' => $request->user()?->name ?? $validated['name'],
            'email' => $request->user()?->email ?: ($validated['email'] ?? null),
            'message' => $validated['message'],
        ]);

        return redirect()->route('feedback.create')->with('status', 'Санал хүсэлтийг хүлээн авлаа. Баярлалаа!');
    }
}
