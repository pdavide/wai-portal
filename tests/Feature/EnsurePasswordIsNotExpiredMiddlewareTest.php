<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Password not expired middleware tests.
 */
class EnsurePasswordIsNotExpiredMiddlewareTest extends TestCase
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

        Route::middleware(['password.not.expired', 'web'])->get('_test/password-expiration', function () {
            return Response::HTTP_OK;
        });

        $this->user = factory(User::class)->create();
    }

    /**
     * Set authorization fail due to expired password.
     */
    public function testExpiredPasswordAuthorizationFail(): void
    {
        $this->user->password_changed_at = Date::now()->subDays(config('auth.password_expiry'));
        $this->user->save();

        $this->actingAs($this->user)
            ->get('_test/password-expiration')
            ->assertRedirect(route('admin.password.change.show'))
            ->assertSessionHas('notification', [
                'title' => __('scadenza password'),
                'message' => __('La password è scaduta e deve essere cambiata.'),
                'status' => 'warning',
                'icon' => 'it-error',
            ]);
    }

    /**
     * Set authorization granted.
     */
    public function testAuthorizationGranted(): void
    {
        $this->actingAs($this->user)
            ->get('_test/password-expiration')
            ->assertOk();
    }
}
