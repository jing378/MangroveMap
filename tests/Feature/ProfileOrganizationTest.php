<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileOrganizationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(User $user, array $overrides = []): array
    {
        return array_merge([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => '09123456789',
        ], $overrides);
    }

    public function test_expert_profile_shows_organization_affiliation_field(): void
    {
        $expert = User::factory()->create(['role' => 'expert', 'organization' => 'SLSU']);

        $this->actingAs($expert)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Organization / Affiliation')
            ->assertSee('id="organization" name="organization"', false)
            ->assertSee('value="SLSU"', false)
            ->assertSee('placeholder="e.g. DENR CENRO Maasin, SLSU"', false);
    }

    public function test_resident_profile_has_no_organization_field(): void
    {
        $resident = User::factory()->create(['role' => 'end_user', 'organization' => 'Pending applicant org']);

        $this->actingAs($resident)->get(route('profile.show'))
            ->assertOk()
            ->assertDontSee('id="organization"', false)
            ->assertDontSee('Organization / Affiliation');
    }

    public function test_expert_can_update_organization(): void
    {
        $expert = User::factory()->create(['role' => 'expert', 'organization' => 'SLSU']);

        $this->actingAs($expert)
            ->putJson(route('profile.update'), $this->payload($expert, ['organization' => '  DENR CENRO Maasin ']))
            ->assertOk()
            ->assertJsonPath('organization', 'DENR CENRO Maasin');

        $this->assertSame('DENR CENRO Maasin', $expert->fresh()->organization);
    }

    public function test_expert_organization_is_required(): void
    {
        $expert = User::factory()->create(['role' => 'expert', 'organization' => 'SLSU']);

        $this->actingAs($expert)
            ->putJson(route('profile.update'), $this->payload($expert, ['organization' => '   ']))
            ->assertStatus(422)
            ->assertJsonPath('errors.organization.0', 'Organization or affiliation is required for expert accounts.');

        $this->assertSame('SLSU', $expert->fresh()->organization);
    }

    public function test_admin_organization_is_optional_and_can_be_cleared(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'organization' => 'MENRO']);

        $this->actingAs($admin)
            ->putJson(route('profile.update'), $this->payload($admin, ['organization' => '']))
            ->assertOk();

        $this->assertNull($admin->fresh()->organization);
    }

    public function test_resident_cannot_set_organization_through_profile(): void
    {
        $resident = User::factory()->create(['role' => 'end_user', 'organization' => null]);

        $this->actingAs($resident)
            ->putJson(route('profile.update'), $this->payload($resident, ['organization' => 'Sneaky Org']))
            ->assertOk();

        $this->assertNull($resident->fresh()->organization);
    }
}
