<?php

namespace Tests\Feature;

use App\Models\DemoVideo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
}