<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Authenticated user redirect middleware tests.
 */
class RedirectIfAuthenticatedMiddlewareTest extends TestCase
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

        Route::middleware('guest')->get('_test/guest', function () {
            return Response::HTTP_OK;
        });
    }

    /**
     * Test not authenticated user not redirected.
     */
    public function testUserNotAuthenticated(): void
    {
        $this->get('_test/guest')
            ->assertOk();
    }

    /**
     * Test authenticated user redirected.
     */
    public function testUserAuthenticatedRedirect(): void
    {
        $this->actingAs($this->user)
            ->get('_test/guest')
            ->assertRedirect(route('analytics'));

        $this->user->allow(UserPermission::ACCESS_ADMIN_AREA);

        $this->actingAs($this->user)
            ->get('_test/guest')
            ->assertRedirect(route('admin.dashboard'));
    }
}
