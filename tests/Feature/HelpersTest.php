<?php

namespace Tests\Feature;

use App\Models\PublicAdministration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Helpers tests.
 */
class HelpersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The logged in user.
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
    }

    /**
     * Post-test cleanup.
     */
    protected function tearDown(): void
    {
        session()->invalidate();
        auth()->logout();
        parent::tearDown();
    }

    /**
     * Test retrieve public administration from user session successful.
     */
    public function testCurrentPublicAdministrationExists(): void
    {
        $publicAdministration = factory(PublicAdministration::class)->create();
        $publicAdministration->users()->sync($this->user->id);

        auth()->login($this->user);
        session()->put('tenant_id', $publicAdministration->id);

        $this->assertNotEmpty(current_public_administration());
    }

    /**
     * Test retrieve public administration from user session fail.
     */
    public function testCurrentPublicAdministrationNotExists(): void
    {
        auth()->login($this->user);
        $this->assertNull(current_public_administration());
    }
}
