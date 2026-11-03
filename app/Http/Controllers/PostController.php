<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(): View
    {
        return view('news.index', ['posts' => Post::query()->published()->latest('published_at')->paginate(12)]);
    }

    public function show(Post $post): View
    {
        abort_unless($post->is_published && ($post->published_at === null || $post->published_at->isPast()), 404);

        return view('news.show', ['post' => $post]);
    }
}
