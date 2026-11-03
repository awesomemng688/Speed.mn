<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Models\VipMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_guests_cannot_open_admin_content_pages(): void
    {
        foreach (['settings', 'posts', 'vip', 'ranks'] as $path) {
            $this->get('/admin/'.$path)->assertRedirect();
        }
    }

    public function test_settings_drive_home_banner_and_title(): void
    {
        $this->actingAs($this->admin())->put('/admin/settings', [
            'discord_url' => 'https://discord.gg/test',
            'hero_title' => 'Шинэ гарчиг',
            'hero_accent' => 'Өнгөт мөр',
            'hero_lead' => 'Тайлбар',
            'banner_enabled' => '1',
            'banner_text' => 'Тэмцээн эхэллээ',
        ])->assertSessionHasNoErrors();

        $this->get('/')->assertSee('Шинэ гарчиг')->assertSee('Тэмцээн эхэллээ')->assertSee('https://discord.gg/test');
        $this->assertSame('https://discord.gg/test', Setting::value('discord_url'));
    }

    public function test_settings_reject_non_https_discord_url(): void
    {
        $this->actingAs($this->admin())->put('/admin/settings', [
            'discord_url' => 'javascript:alert(1)',
            'hero_title' => 'a', 'hero_accent' => 'b', 'hero_lead' => 'c',
        ])->assertSessionHasErrors('discord_url');
    }

    public function test_admin_can_publish_post_and_public_can_read_it(): void
    {
        $this->actingAs($this->admin())->post('/admin/posts', [
            'type' => 'tournament', 'title' => 'Speed Cup', 'body' => 'Дэлгэрэнгүй', 'is_published' => '1',
        ])->assertRedirect();

        $post = Post::firstOrFail();
        auth()->logout();
        $this->get('/news')->assertOk()->assertSee('Speed Cup');
        $this->get('/news/'.$post->slug)->assertOk()->assertSee('Дэлгэрэнгүй');
    }

    public function test_draft_post_is_hidden(): void
    {
        Post::create(['type' => 'news', 'title' => 'Draft', 'slug' => 'draft', 'body' => 'x', 'is_published' => false]);

        $this->get('/news')->assertDontSee('Draft');
        $this->get('/news/draft')->assertNotFound();
    }

    public function test_admin_can_add_vip_member(): void
    {
        $this->actingAs($this->admin())->post('/admin/vip', [
            'steam_id' => '76561198000000000', 'name' => 'Bat', 'plan' => 'VIP', 'amount' => 10000,
            'starts_at' => now()->toDateString(), 'expires_at' => now()->addMonth()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertTrue(VipMember::firstOrFail()->isActive());
        $this->get('/vip')->assertSee('Bat');
    }
}
