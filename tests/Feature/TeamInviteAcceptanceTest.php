<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\TeamInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamInviteAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_invite_can_be_accepted_into_business(): void
    {
        $business = Business::query()->create([
            'name' => 'Invite Clinic',
            'business_type' => 'clinic',
            'slug' => 'invite-clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'pro',
        ]);

        $token = Str::random(64);

        $invite = TeamInvite::query()->create([
            'business_id' => $business->id,
            'email' => 'joiner@example.com',
            'role' => 'manager',
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
            'expires_at' => now()->addDays(3),
        ]);

        $response = $this->post(route('team-invites.accept', $token), [
            'name' => 'Joined User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'business_id' => $business->id,
            'email' => 'joiner@example.com',
            'role' => 'manager',
        ]);

        $this->assertDatabaseHas('team_invites', [
            'id' => $invite->id,
            'status' => 'accepted',
        ]);
    }
}
