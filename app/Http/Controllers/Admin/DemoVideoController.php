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
use Illuminate\Support\Facades\File;
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

        $sourceDir = realpath((string) config('services.cs2_demo_parser.source_dir'));
        $sourceAvailable = $sourceDir !== false && is_dir($sourceDir) && is_readable($sourceDir);
        $availableDemFiles = collect();

        if ($sourceAvailable) {
            $importedNames = DemoVideo::query()
                ->where('media_type', 'dem')
                ->whereIn('processing_status', ['queued', 'processing', 'ready'])
                ->pluck('original_filename')
                ->flip();

            $availableDemFiles = collect(File::files($sourceDir))
                ->filter(fn (\SplFileInfo $file) => strtolower($file->getExtension()) === 'dem'
                    && $file->isReadable()
                    && $file->getSize() > 0
                    && $file->getSize() <= 500 * 1024 * 1024)
                ->map(fn (\SplFileInfo $file) => [
                    'name' => $file->getFilename(),
                    'size' => $file->getSize(),
                    'modified_at' => $file->getMTime(),
                    'imported' => $importedNames->has($file->getFilename()),
                ])
                ->sortByDesc('modified_at')
                ->values();
        }

        return view('admin.demos.index', compact('demos', 'availableDemFiles', 'sourceAvailable'));
    }

    public function importFromMatchZy(Request $request, AdminAuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate(['filename' => ['required', 'string', 'max:255']]);
        $filename = $validated['filename'];
        abort_unless(basename($filename) === $filename && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'dem', 404);

        $sourceDir = realpath((string) config('services.cs2_demo_parser.source_dir'));
        abort_unless($sourceDir !== false && is_dir($sourceDir), 503, 'MatchZy demo folder is unavailable.');
        $sourcePath = realpath($sourceDir.DIRECTORY_SEPARATOR.$filename);
        abort_unless($sourcePath !== false
            && str_starts_with($sourcePath, rtrim($sourceDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            && is_file($sourcePath)
            && is_readable($sourcePath), 404);

        $size = filesize($sourcePath);
        abort_unless($size !== false && $size > 0 && $size <= 500 * 1024 * 1024, 422, 'Demo must be between 1 byte and 500 MiB.');

        $existing = DemoVideo::query()
            ->where('media_type', 'dem')
            ->where('original_filename', $filename)
            ->whereIn('processing_status', ['queued', 'processing', 'ready'])
            ->first();
        if ($existing) {
            return back()->with('status', 'Энэ demo өмнө нь import хийгдсэн байна.');
        }

        $disk = Storage::disk('local');
        $path = 'demo-videos/'.Str::uuid().'.dem';
        $disk->makeDirectory('demo-videos');
        if (! copy($sourcePath, $disk->path($path))) {
            return back()->withErrors(['filename' => 'Demo-г private storage руу хуулж чадсангүй.']);
        }

        try {
            $demo = DB::transaction(function () use ($request, $audit, $filename, $path): DemoVideo {
                $title = pathinfo($filename, PATHINFO_FILENAME);
                preg_match('/(?:^|[_-])(de_[a-z0-9_]+?)(?=_(?:team|vs|match|final|scrim)(?:_|$)|$)/i', $title, $mapMatch);
                $demo = DemoVideo::create([
                    'title' => Str::limit(str_replace(['_', '-'], ' ', $title), 180, ''),
                    'original_filename' => $filename,
                    'file_path' => $path,
                    'media_type' => 'dem',
                    'processing_status' => 'queued',
                    'map' => isset($mapMatch[1]) ? Str::limit($mapMatch[1], 64, '') : null,
                    'uploaded_by' => $request->user()->id,
                ]);

                $audit->record($request, 'demo.parser_imported', $demo, ['source' => 'matchzy']);

                return $demo;
            });

            ParseCs2Demo::dispatch($demo->id)->afterCommit();
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }

        return redirect()->route('admin.demos.index')->with('status', 'MatchZy demo parser queue-д орлоо.');
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