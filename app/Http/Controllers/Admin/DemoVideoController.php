<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ParseCs2Demo;
use App\Models\DemoVideo;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
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
        $demos->getCollection()->transform(function (DemoVideo $demo): DemoVideo {
            if ($demo->analysis_path && $demo->processing_status === 'ready') {
                try {
                    $demo->analysis_data = json_decode(
                        Storage::disk('local')->get($demo->analysis_path),
                        true,
                        512,
                        JSON_THROW_ON_ERROR,
                    );
                } catch (Throwable $exception) {
                    Log::warning('Could not read parsed demo analysis', [
                        'demo_video_id' => $demo->id,
                        'error' => $exception->getMessage(),
                    ]);
                    $demo->analysis_data = null;
                }
            }

            return $demo;
        });

        return view('admin.demos.index', compact('demos'));
    }

    public function store(Request $request, AdminAuditLogger $audit): RedirectResponse
    {
        if ($request->hasFile('video') === $request->hasFile('demo')) {
            return back()
                ->withErrors(['video' => 'MP4 видео эсвэл .dem demo-гийн аль нэгийг сонгоно уу.'])
                ->withInput();
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'map' => ['nullable', 'string', 'max:64'],
            'recorded_at' => ['nullable', 'date'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4', 'max:1048576'],
            'demo' => ['nullable', 'file', 'extensions:dem', 'max:512000'],
        ]);

        $file = $request->file('video') ?? $request->file('demo');
        $mediaType = $request->hasFile('demo') ? 'dem' : 'mp4';
        $path = $file->store('demo-videos', 'local');
        if (! $path) {
            return back()->withErrors(['video' => 'Бичлэгийг private storage-д хадгалж чадсангүй.'])->withInput();
        }

        try {
            $demo = DB::transaction(function () use ($request, $validated, $file, $path, $mediaType, $audit): DemoVideo {
                $demo = DemoVideo::create([
                    'title' => $validated['title'],
                    'map' => $validated['map'] ?? null,
                    'recorded_at' => $validated['recorded_at'] ?? null,
                    'original_filename' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                    'file_path' => $path,
                    'media_type' => $mediaType,
                    'processing_status' => $mediaType === 'dem' ? 'queued' : 'ready',
                    'uploaded_by' => $request->user()->id,
                ]);

                $audit->record($request, 'demo.video_uploaded', $demo, [
                    'fields' => ['title', 'map', 'recorded_at', 'original_filename'],
                ]);

                return $demo;
            });

            if ($mediaType === 'dem') {
                ParseCs2Demo::dispatch($demo->id)->afterCommit();
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return redirect()->route('admin.demos.index')->with(
            'status',
            $mediaType === 'dem' ? 'CS2 demo parser queue-д орлоо.' : 'MP4 demo сан руу нэмэгдлээ.',
        );
    }

    public function stream(DemoVideo $demo): BinaryFileResponse
    {
        abort_unless($demo->media_type === 'mp4', 404);
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
        if ($demo->analysis_path) {
            Storage::disk('local')->delete($demo->analysis_path);
        }
        $demo->delete();

        return redirect()->route('admin.demos.index')->with('status', 'Demo устгагдлаа.');
    }
}