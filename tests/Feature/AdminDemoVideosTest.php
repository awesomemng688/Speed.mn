<?php

namespace Tests\Feature;

use App\Jobs\ParseCs2Demo;
use App\Models\DemoVideo;
use App\Models\User;
use App\Services\Cs2DemoParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDemoVideosTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_and_play_private_mp4(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAsAdmin($admin)
            ->post(route('admin.demos.store'), [
                'title' => 'Team A vs Team B',
                'map' => 'de_dust2',
                'recorded_at' => '2026-10-05T01:05',
                'video' => UploadedFile::fake()->create('match.mp4', 100, 'video/mp4'),
            ])
            ->assertRedirect(route('admin.demos.index'));

        $demo = DemoVideo::sole();
        $this->assertSame($admin->id, $demo->uploaded_by);
        Storage::disk('local')->assertExists($demo->file_path);

        $this->actingAsAdmin($admin)
            ->get(route('admin.demos.stream', $demo))
            ->assertOk()
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('Content-Disposition', 'inline; filename="team-a-vs-team-b.mp4"');
    }

    public function test_guests_and_non_admins_cannot_access_demo_library(): void
    {
        $demo = DemoVideo::create([
            'title' => 'Private demo',
            'original_filename' => 'private.mp4',
            'file_path' => 'demo-videos/private.mp4',
        ]);

        $this->get(route('admin.demos.index'))->assertRedirect(route('steam.login'));

        $this->actingAs(User::factory()->create())
            ->get(route('admin.demos.stream', $demo))
            ->assertForbidden();
    }

    public function test_raw_dem_files_are_not_accepted_as_browser_videos(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAsAdmin($admin)
            ->from(route('admin.demos.index'))
            ->post(route('admin.demos.store'), [
                'title' => 'Raw demo',
                'video' => UploadedFile::fake()->create('match.dem', 100, 'application/octet-stream'),
            ])
            ->assertRedirect(route('admin.demos.index'))
            ->assertSessionHasErrors('video');
    }

    public function test_admin_can_upload_a_dem_for_queued_parsing(): void
    {
        Storage::fake('local');
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAsAdmin($admin)
            ->post(route('admin.demos.store'), [
                'title' => 'MatchZy demo',
                'map' => 'de_dust2',
                'demo' => UploadedFile::fake()->create('match.dem', 1024, 'application/octet-stream'),
            ])
            ->assertRedirect(route('admin.demos.index'));

        $demo = DemoVideo::sole();
        $this->assertSame('dem', $demo->media_type);
        $this->assertSame('queued', $demo->processing_status);
        Storage::disk('local')->assertExists($demo->file_path);
        Queue::assertPushed(ParseCs2Demo::class, fn (ParseCs2Demo $job) => $job->demoVideoId === $demo->id
            && $job->queue === 'demo-parsing');
    }

    public function test_parser_job_stores_analysis_and_admin_page_displays_match_stats(): void
    {
        Storage::fake('local');
        $demoPath = UploadedFile::fake()->create('match.dem', 1024, 'application/octet-stream')->store('demo-videos', 'local');
        $demo = DemoVideo::create([
            'title' => 'Parsed match',
            'original_filename' => 'match.dem',
            'file_path' => $demoPath,
            'media_type' => 'dem',
            'processing_status' => 'queued',
        ]);
        config([
            'services.cs2_demo_parser.python' => PHP_BINARY,
            'services.cs2_demo_parser.entrypoint' => base_path('tests/Fixtures/fake-cs2-demo-parser.php'),
            'services.cs2_demo_parser.timeout_seconds' => 60,
        ]);

        (new ParseCs2Demo($demo->id))->handle(app(Cs2DemoParser::class));

        $demo->refresh();
        $this->assertSame('ready', $demo->processing_status);
        $this->assertNotNull($demo->analysis_path);
        $analysis = json_decode(Storage::disk('local')->get($demo->analysis_path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $analysis['statistics']['total_kills']);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAsAdmin($admin)
            ->get(route('admin.demos.index'))
            ->assertOk()
            ->assertSee('Parsed match')
            ->assertSee('de_dust2')
            ->assertSee('Team A')
            ->assertSee('Player A')
            ->assertSee('1');
    }
}