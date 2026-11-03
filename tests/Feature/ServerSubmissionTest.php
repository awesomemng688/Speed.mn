<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $over = []): array
    {
        return $over + ['name' => 'My Server', 'game' => 'cs2', 'ip' => '8.8.4.4', 'port' => 27015, 'max_players' => 32];
    }

    public function test_guest_is_redirected(): void
    {
        $this->post('/submit-server', $this->payload())->assertRedirect();
        $this->assertSame(0, ServerSubmission::count());
    }

    public function test_user_can_submit_but_not_private_ip(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/submit-server', $this->payload(['ip' => '192.168.1.5']))->assertSessionHasErrors('ip');
        $this->actingAs($user)->post('/submit-server', $this->payload())->assertRedirect(route('submissions.create'));
        $this->assertSame(1, ServerSubmission::where('status', 'pending')->count());
    }

    public function test_non_admin_cannot_approve(): void
    {
        $user = User::factory()->create();
        $submission = ServerSubmission::create($this->payload() + ['user_id' => $user->id]);

        $this->actingAs($user)->post(route('admin.submissions.approve', $submission))->assertStatus(403);
        $this->assertSame(0, Server::count());
    }
}
