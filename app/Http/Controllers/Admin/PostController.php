<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $escaped = addcslashes(mb_substr($search, 0, 100), '%_\\');
        $posts = Post::query()
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$escaped}%"))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.posts.index', ['posts' => $posts, 'search' => $search]);
    }

    public function create(): View
    {
        return view('admin.posts.form', ['post' => new Post(['type' => 'news'])]);
    }

    public function store(Request $request, AdminAuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['title']);
        $post = Post::create($data);
        $audit->record($request, 'post.created', null, [], $post->title);

        return redirect()->route('admin.posts.index')->with('status', 'Нийтлэл нэмэгдлээ.');
    }

    public function edit(Post $post): View
    {
        return view('admin.posts.form', ['post' => $post]);
    }

    public function update(Request $request, Post $post, AdminAuditLogger $audit): RedirectResponse
    {
        $post->update($this->validated($request));
        $audit->record($request, 'post.updated', null, [], $post->title);

        return redirect()->route('admin.posts.index')->with('status', 'Нийтлэл хадгалагдлаа.');
    }

    public function destroy(Request $request, Post $post, AdminAuditLogger $audit): RedirectResponse
    {
        $audit->record($request, 'post.deleted', null, [], $post->title);
        $post->delete();

        return redirect()->route('admin.posts.index')->with('status', 'Нийтлэл устгагдлаа.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'in:news,tournament'],
            'title' => ['required', 'string', 'max:160'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:20000'],
            'image_url' => ['nullable', 'url', 'max:255', 'starts_with:https://,http://'],
            'event_at' => ['nullable', 'date'],
        ]);
        $data['is_published'] = $request->boolean('is_published');
        $data['published_at'] = $data['is_published'] ? ($request->route('post')?->published_at ?? now()) : null;

        return $data;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $i = 2;
        while (Post::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
