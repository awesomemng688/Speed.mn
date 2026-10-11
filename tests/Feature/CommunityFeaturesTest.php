<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommunityFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_rules_page_is_available_and_linked_from_the_site_footer(): void
    {
        $this->get(route('rules'))
            ->assertOk()
            ->assertSee('Серверийн дүрэм')
            ->assertSee(route('feedback.create'));
    }

    public function test_guest_can_send_feedback_for_admin_review(): void
    {
        $this->post(route('feedback.store'), [
            'name' => 'Тоглогч',
            'email' => 'player@example.com',
            'message' => 'Серверийн жагсаалтад шинэ шүүлтүүр нэмнэ үү.',
        ])->assertRedirect(route('feedback.create'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('feedback', [
            'name' => 'Тоглогч',
            'email' => 'player@example.com',
            'status' => 'new',
        ]);
    }

    public function test_feedback_requires_a_valid_message(): void
    {
        $this->from(route('feedback.create'))
            ->post(route('feedback.store'), [
                'name' => 'Тоглогч',
                'email' => 'not-an-email',
                'message' => 'Сайн',
            ])
            ->assertSessionHasErrors(['email', 'message']);

        $this->assertDatabaseCount('feedback', 0);
    }

    public function test_only_admins_can_review_feedback_and_update_its_status(): void
    {
        $feedback = Feedback::create([
            'name' => 'Тоглогч',
            'message' => 'Санал хүсэлтийн шалгалт.',
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.feedback.index'))
            ->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAsAdmin($admin)
            ->get(route('admin.feedback.index'))
            ->assertOk()
            ->assertSee('Санал хүсэлтийн шалгалт.');

        $this->actingAsAdmin($admin)
            ->patch(route('admin.feedback.update', $feedback), ['status' => 'reviewing'])
            ->assertRedirect(route('admin.feedback.index'));

        $this->assertSame('reviewing', $feedback->fresh()->status);
    }

    public function test_ranked_player_name_links_to_public_profile_with_rank_statistics(): void
    {
        $steamId = '76561198000000001';
        $this->configurePrimaryRankConnection();
        DB::connection('rank_public_1')->table('rank_system_new')->insert([
            'steamid' => $steamId,
            'Nick' => 'Ranked player',
            'name' => 'Ranked player',
            'XP' => 1250,
            'kills' => 50,
            'deaths' => 20,
        ]);

        $this->get(route('ranks.index', 'public-1'))
            ->assertOk()
            ->assertSee(route('players.show', $steamId), false);

        $this->get(route('players.show', $steamId))
            ->assertOk()
            ->assertSee('Ranked player')
            ->assertSee('XP 1,250');
    }

    public function test_public_player_profile_rejects_invalid_steam_ids(): void
    {
        $this->get('/players/not-a-steam-id')->assertNotFound();
    }

    private function configurePrimaryRankConnection(): void
    {
        config(['services.steam.key' => '']);
        config(['database.connections.rank_public_1' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::purge('rank_public_1');

        Schema::connection('rank_public_1')->create('rank_system_new', function ($table): void {
            $table->increments('id');
            $table->string('steamid')->nullable();
            $table->string('Nick')->nullable();
            $table->string('name')->nullable();
            $table->unsignedInteger('XP')->default(0);
            $table->unsignedInteger('kills')->default(0);
            $table->unsignedInteger('deaths')->default(0);
        });
    }
}
