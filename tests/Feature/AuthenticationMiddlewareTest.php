<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Italia\SPIDAuth\SPIDUser;
use Tests\TestCase;

/**
 * Users authentication middleware tests.
 */
class AuthenticationMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The authenticating user.
     *
     * @var User the user
     */
    private $user;

    /**
     * The mocked SPID user information.
     *
     * @var SPIDUser the SPID user
     */
    private $spidUser;

    /**
     * Pre-test setup.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth', 'web'])->get('_test/authentication', function () {
            return Response::HTTP_OK;
        });

        $this->user = factory(User::class)->create();
        $this->spidUser = new SPIDUser([
            'fiscalNumber' => ['TINIT-' . $this->user->fiscal_number],
        ]);
    }

    /**
     * Test missing user redirect to registration.
     */
    public function testMissingUserAuthentication(): void
    {
        $this->withSession([
            'spid_user' => new SPIDUser(['fiscalNumber' => ['TINIT-FAKEFISCALNUMBER']]),
        ])
            ->get('_test/authentication')
            ->assertRedirect(route('auth.register.show'));
    }

    /**
     * Test authentication successful.
     */
    public function testUserAuthenticationSuccessful(): void
    {
        $this->actingAs($this->user)
            ->withSession(['spid_user' => $this->spidUser])
            ->get('_test/authentication')
            ->assertOk();
    }

    /**
     * Test deleted user redirect to home.
     */
    public function testDeletedUserAuthentication(): void
    {
        $this->user->delete();

        $this->withSession(['spid_user' => $this->spidUser])
            ->get('_test/authentication')
            ->assertSessionHas('notification', [
                'title' => __('accesso negato'),
                'message' => __("L'utenza è stata rimossa."),
                'status' => 'error',
                'icon' => 'it-close-circle',
            ])
            ->assertRedirect(route('home'));
    }
}
