<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Super-admin users authentication middleware tests.
 */
class AuthenticateAdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The super-admin user.
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

        Route::middleware('auth.admin')->get('_test/admin-authentication', function () {
            return Response::HTTP_OK;
        });
    }

    /**
     * Test missing authentication redirect to login.
     */
    public function testMissingSuperAdminAuthentication(): void
    {
        $this->get('_test/admin-authentication')
            ->assertRedirect(route('admin.login.show'));
    }

    /**
     * Test authentication successful.
     */
    public function testSuperAdminAuthenticationSuccessful(): void
    {
        $this->user->assign(UserRole::SUPER_ADMIN);

        $this->actingAs($this->user)
            ->get('_test/admin-authentication')
            ->assertOk();
    }

    /**
     * Test wrong user role authorization error.
     */
    public function testNotSuperAdminAuthentication(): void
    {
        $this->withoutExceptionHandling();

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Current user in not a super administrator.');

        $this->actingAs($this->user)
            ->get('_test/admin-authentication');
    }
}
