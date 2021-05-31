<?php

namespace Tests\Feature;

use App\Enums\CredentialPermission;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Credential;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use Faker\Factory;
use Faker\Generator;
use Faker\Provider\it_IT\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Italia\SPIDAuth\SPIDUser;
use Ramsey\Uuid\Uuid;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Credential CRUD test.
 */
class CRUDCredentialTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The user.
     *
     * @var User the user
     */
    private $user;

    /**
     * The SPID user.
     *
     * @var SPIDUser the SPID user
     */
    private $spidUser;

    /**
     * The public administration.
     *
     * @var PublicAdministration the public administration
     */
    private $publicAdministration;

    /**
     * The website.
     *
     * @var Website the website
     */
    private $website;

    /**
     * Fake data generator.
     *
     * @var Generator the generator
     */
    private $faker;

    /**
     * Pre-test setup.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->user = factory(User::class)->create([
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => Date::now(),
        ]);
        $this->publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();
        $this->publicAdministration->users()->sync([$this->user->id => ['user_email' => $this->user->email, 'user_status' => UserStatus::ACTIVE]]);

        $this->website = factory(Website::class)->create([
            'public_administration_id' => $this->publicAdministration->id,
        ]);

        $this->spidUser = new SPIDUser([
            'fiscalNumber' => $this->user->fiscal_number,
            'familyName' => $this->user->family_name,
            'name' => $this->user->name,
        ]);

        $this->credential = factory(Credential::class)->create([
            'public_administration_id' => $this->publicAdministration->id,
        ]);

        Bouncer::dontCache();
        Bouncer::scope()->to($this->publicAdministration->id);
        $this->user->assign(UserRole::ADMIN);
        $this->user->allow(UserPermission::MANAGE_USERS);
        $this->user->allow(UserPermission::MANAGE_WEBSITES);

        $this->faker = Factory::create();
        $this->faker->addProvider(new Person($this->faker));

        Http::fake([
            'kong:8001/consumers/' . $this->credential->consumer_id => Http::response([
                'custom_id' => '{"name":"Chiave1","type":"admin","siteId":[]}',
                'id' => $this->credential->consumer_id,
                'username' => $this->credential->client_name,
            ], 200),
        ]);
    }

    /**
     * Test JSON data for datatable successful.
     */
    public function testDatatableData(): void
    {
        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
            ])
            ->json('GET', route('api-credentials.data.json'))
            ->assertOk()
            ->assertJsonFragment([
                'raw' => e($this->credential->client_name),
            ]);
    }

    /**
     * Create admin type credentials.
     *
     * @return void
     */
    public function testCreateAdminCredentialSuccessful(): void
    {
        $name = $this->faker->words(5, true);
        $idConsumer = Uuid::uuid4()->toString();
        $idOauth = Uuid::uuid4()->toString();

        Http::fake([
            'kong:8001/consumers' => Http::response([
                'custom_id' => '{"name":"Chiave1","type":"admin","siteId":[]}',
                'id' => $idConsumer,
                'username' => $name,
            ], 200),

            'kong:8001/consumers/' . rawurlencode($name) . '/oauth2' => Http::response([
                'id' => $idOauth,
                'name' => $name . '-oauth2',
                'client_secret' => 'v0JYTMLJl99r27XLEydFcVag9jAGT1Oe',
                'client_id' => 'RlwewJa40Felwm9inHleluLrzSpNdVMO',
                'consumer' => [
                    'id' => $idConsumer,
                ],
            ], 200),
        ]);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->post(route('api-credentials.store'), [
                'credential_name' => $name,
                'type' => 'admin',
                'permissions' => [],
            ])
            ->assertSessionDoesntHaveErrors([
                'credential_name',
                'type',
                'permissions',
            ])
            ->assertRedirect(route('api-credentials.index'));
    }

    /**
     * Create Analytics credentials.
     *
     * @return void
     */
    public function testCreateAnalyticsCredentialSuccessful(): void
    {
        $name = $this->faker->words(5, true);
        $idConsumer = Uuid::uuid4()->toString();
        $idOauth = Uuid::uuid4()->toString();

        Http::fake([
            'kong:8001/consumers' => Http::response([
                'custom_id' => '{"name":"Chiave1","type":"analytics","siteId":[{"id":1,"permission":"R"}]}',
                'id' => $idConsumer,
                'username' => $name,
            ], 200),

            'kong:8001/consumers/' . rawurlencode($name) . '/oauth2' => Http::response([
                'id' => $idOauth,
                'name' => $name . '-oauth2',
                'client_secret' => 'v0JYTMLJl99r27XLEydFcVag9jAGT1Oe',
                'client_id' => 'RlwewJa40Felwm9inHleluLrzSpNdVMO',
                'consumer' => [
                    'id' => $idConsumer,
                ],
            ], 200),
        ]);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->post(route('api-credentials.store'), [
                'credential_name' => $name,
                'type' => 'analytics',
                'permissions' => [
                    $this->website->analytics_id => [CredentialPermission::READ],
                ],
            ])
            ->assertSessionDoesntHaveErrors([
                'credential_name',
                'type',
                'permissions',
            ])
            ->assertRedirect(route('api-credentials.index'));
    }

    /**
     * Test credential creation fails due to fields validation.
     */
    public function testCreateAnalyticsCredentialFailValidation(): void
    {
        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->from(route('api-credentials.create'))
            ->post(route('api-credentials.store'), [
                'credential_name' => $this->credential->client_name,
                'type' => 'analytics',
            ])
            ->assertRedirect(route('api-credentials.create'))
            ->assertSessionHasErrors([
                'credential_name',
                'permissions',
            ]);
    }

    /**
     * Update Credentials' name.
     *
     * @return void
     */
    public function testUpdateCredentialPermissionsAndNameSuccessful(): void
    {
        $name = $this->faker->words(5, true);
        $idOauth = Uuid::uuid4()->toString();

        Http::fake([
            'kong:8001/consumers' => Http::response([
                'custom_id' => '{"name":"Chiave1","type":"analytics","siteId":[{"id":1,"permission":"R"}]}',
                'id' => $this->credential->consumer_id,
                'username' => $name,
            ], 200),

            'kong:8001/consumers/' . rawurlencode($name) . '/oauth2' => Http::response([
                'id' => $idOauth,
                'name' => $name . '-oauth2',
                'client_secret' => 'v0JYTMLJl99r27XLEydFcVag9jAGT1Oe',
                'client_id' => 'RlwewJa40Felwm9inHleluLrzSpNdVMO',
                'consumer' => [
                    'id' => $this->credential->consumer_id,
                ],
            ], 200),
        ]);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->put(route('api-credentials.update', ['credential' => $this->credential]), [
                'credential_name' => $name,
                'type' => 'analytics',
                'permissions' => [
                    $this->website->analytics_id => [CredentialPermission::READ, CredentialPermission::WRITE],
                ],
            ])
            ->assertSessionDoesntHaveErrors([
                'credential_name',
                'type',
                'permissions',
            ])
            ->assertRedirect(route('api-credentials.index'));

        $this->credential->refresh();
    }

    /**
     * Test credential update fails due to fields validation.
     */
    public function testUpdateAnalyticsCredentialFailValidation(): void
    {
        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->from(route('api-credentials.edit', ['credential' => $this->credential]))
            ->put(route('api-credentials.update', ['credential' => $this->credential]), [
                'credential_name' => $this->credential->client_name,
                'type' => 'analytics',
            ])
            ->assertRedirect(route('api-credentials.edit', ['credential' => $this->credential]))
            ->assertSessionHasErrors([
                'permissions',
            ]);
    }

    /**
     * Delete Credentials.
     *
     * @return void
     */
    public function testDeleteAnalyticsCredentialSuccessful(): void
    {
        $credentialToDelete = factory(Credential::class)->create([
            'public_administration_id' => $this->publicAdministration->id,
        ]);

        Http::fake([
            'kong:8001/consumers/' . $credentialToDelete->consumer_id => Http::response([
                'id' => $credentialToDelete->consumer_id,
                'username' => $credentialToDelete->client_name,
            ], 200),
        ]);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->patch(route('api-credentials.delete', ['credential' => $credentialToDelete]), [])
            ->assertStatus(302)
            ->assertRedirect(route('home'));
    }

    /**
     * Delete a credential.
     *
     * @return void
     */
    public function testDeleteCredentialSuccessful(): void
    {
        $credentialToDelete = factory(Credential::class)->create([
            'public_administration_id' => $this->publicAdministration->id,
        ]);

        Http::fake([
            'kong:8001/consumers/' . $credentialToDelete->consumer_id => Http::response([
                'id' => $credentialToDelete->consumer_id,
                'username' => $credentialToDelete->client_name,
            ], 200),
        ]);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->patch(route('api-credentials.delete', ['credential' => $credentialToDelete]), [])
            ->assertRedirect(route('home'));
    }

    /**
     * Regenerate the credentials.
     *
     * @return void
     */
    public function testRegenerateCredentialSuccessful(): void
    {
        $idOauth = Uuid::uuid4()->toString();
        $oauthData = [
            'id' => $idOauth,
            'name' => $this->credential->client_name . '-oauth2',
            'client_secret' => 'v0JYTMLJl99r27XLEydFcVag9jAGT1Oe',
            'client_id' => 'RlwewJa40Felwm9inHleluLrzSpNdVMO',
            'consumer' => [
                'id' => $this->credential->consumer_id,
            ],
        ];

        Http::fake([
            'kong:8001/consumers' => Http::response([
                'custom_id' => '{"name":"Chiave1","type":"analytics","siteId":[{"id":1,"permission":"R"}]}',
                'id' => $this->credential->consumer_id,
                'username' => $this->credential->client_name,
            ], 200),

            'kong:8001/consumers/' . $this->credential->consumer_id . '/oauth2' => Http::response(['data' => [$oauthData]], 200),
            'kong:8001/consumers/' . rawurlencode($this->credential->client_name) . '/oauth2/RlwewJa40Felwm9inHleluLrzSpNdVMO' => Http::response($oauthData, 200),
            'kong:8001/oauth2_tokens/' => Http::response([
                'data' => [
                    [
                        'id' => 'fake_token_id',
                        'credential' => [
                            'id' => $idOauth,
                        ],
                    ],
                    [
                        'id' => 'fake_token_id2',
                        'credential' => [
                            'id' => $idOauth,
                        ],
                    ],
                ],
            ], 200),
            'kong:8001/oauth2_tokens/fake_token_id' => Http::response(['data' => true], 200),
            'kong:8001/oauth2_tokens/fake_token_id2' => Http::response(['data' => true], 200),
        ]);

        $this->actingAs($this->user)
            ->withSession([
                'spid_sessionIndex' => 'fake-session-index',
                'tenant_id' => $this->publicAdministration->id,
                'spid_user' => $this->spidUser,
                '_token' => 'test',
            ])
            ->get(route('api-credentials.regenerate', ['credential' => $this->credential]), [])
            ->assertRedirect(route('api-credentials.show', ['credential' => $this->credential]));
    }
}
