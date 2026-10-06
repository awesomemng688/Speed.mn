<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DemoVideo;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DemoVideoController extends Controller
{
    public function index(): View
    {
        $demos = DemoVideo::query()
            ->with('uploadedBy:id,name')
            ->latest()
            ->paginate(10);

        return view('admin.demos.index', compact('demos'));
    }

    public function store(Request $request, AdminAuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'map' => ['nullable', 'string', 'max:64'],
            'recorded_at' => ['nullable', 'date'],
            'video' => ['required', 'file', 'mimetypes:video/mp4', 'max:1048576'],
        ]);

        $file = $request->file('video');
        $path = $file->store('demo-videos', 'local');
        if (! $path) {
            return back()->withErrors(['video' => 'Бичлэгийг private storage-д хадгалж чадсангүй.'])->withInput();
        }

        try {
            DB::transaction(function () use ($request, $validated, $file, $path, $audit): void {
                $demo = DemoVideo::create([
                    'title' => $validated['title'],
                    'map' => $validated['map'] ?? null,
                    'recorded_at' => $validated['recorded_at'] ?? null,
                    'original_filename' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                    'file_path' => $path,
                    'uploaded_by' => $request->user()->id,
                ]);

                $audit->record($request, 'demo.video_uploaded', $demo, [
                    'fields' => ['title', 'map', 'recorded_at', 'original_filename'],
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return redirect()->route('admin.demos.index')->with('status', 'MP4 demo сан руу нэмэгдлээ.');
    }

    public function stream(DemoVideo $demo): BinaryFileResponse
    {
        $path = Storage::disk('local')->path($demo->file_path);
        abort_unless(is_file($path), 404);

        $filename = Str::slug($demo->title) ?: 'cs2-demo';

        return response()->file($path, [
            'Content-Type' => 'video/mp4',
            'Content-Disposition' => 'inline; filename="'.$filename.'.mp4"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(DemoVideo $demo, Request $request, AdminAuditLogger $audit): RedirectResponse
    {
        $audit->record($request, 'demo.video_deleted', $demo);
        Storage::disk('local')->delete($demo->file_path);
        $demo->delete();

        return redirect()->route('admin.demos.index')->with('status', 'Demo устгагдлаа.');
    }
}