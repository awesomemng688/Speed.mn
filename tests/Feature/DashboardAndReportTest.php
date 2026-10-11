<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardAndReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertRedirect();
    }

    public function test_daily_report_is_skipped_without_webhook(): void
    {
        config(['services.discord.admin_webhook' => null]);
        Http::fake();

        $this->assertSame(0, Artisan::call('speedmn:daily-report'));
        Http::assertNothingSent();
    }

    public function test_daily_report_posts_summary(): void
    {
        config(['services.discord.admin_webhook' => 'https://discord.test/hook']);
        Http::fake();
        $server = Server::query()->create(['name' => 'A', 'game' => 'cs2', 'ip' => '127.0.0.1', 'port' => 27015, 'max_players' => 10, 'enabled' => true]);
        ServerStatus::query()->create(['server_id' => $server->id, 'online' => true, 'players' => 4, 'max_players' => 10, 'created_at' => now()->subMinutes(5)]);

        $this->assertSame(0, Artisan::call('speedmn:daily-report'));
        Http::assertSent(fn ($request) => str_contains($request['content'], 'Оргил онлайн: 4'));
    }
}
