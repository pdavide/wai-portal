<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\PublicAdministration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Tenant select middleware tests.
 */
class SelectTenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The authenticated user.
     *
     * @var User the user
     */
    private $user;

    /**
     * Pre-test setup.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        Bouncer::dontCache();

        Route::middleware(['web', 'select.tenant', 'authorize.public.administrations'])->get('_test/tenant-select/{publicAdministration?}', function (?PublicAdministration $publicAdministration) {
            return Response::HTTP_OK;
        });
    }

    /**
     * Test tenant not enforced.
     */
    public function testNotAuthenticated(): void
    {
        $this->get('_test/tenant-select')
            ->assertSessionMissing('tenant_id')
            ->assertOk();
    }

    /**
     * Test tenant enforced for user.
     */
    public function testUserTenantEnforced(): void
    {
        $publicAdministration = factory(PublicAdministration::class)->create();
        $publicAdministration->users()->sync([$this->user->id => ['user_status' => UserStatus::ACTIVE]]);

        $this->actingAs($this->user)
            ->get('_test/tenant-select')
            ->assertSessionHas('tenant_id', $publicAdministration->id);
    }

    /**
     * Test tenant not enforced for super-admin.
     */
    public function testSuperUserTenantNotEnforce(): void
    {
        $this->user->assign(UserRole::SUPER_ADMIN);

        $this->actingAs($this->user)
            ->get('_test/tenant-select')
            ->assertSessionMissing(['tenant_id', 'super_admin_tenant_ipa_code']);
    }

    /**
     * Test tenant configuration for super-admin for public administrations routes.
     */
    public function testSuperUserTenantEnforced(): void
    {
        $publicAdministration = factory(PublicAdministration::class)->create();
        $this->user->assign(UserRole::SUPER_ADMIN);

        $this->actingAs($this->user)
            ->get('_test/tenant-select/' . $publicAdministration->ipa_code)
            ->assertSessionMissing('tenant_id', 'super_admin_tenant_ipa_code');
    }
}
