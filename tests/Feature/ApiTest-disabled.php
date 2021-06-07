<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WebsiteAccessType;
use App\Enums\WebsiteStatus;
use App\Enums\WebsiteType;
use App\Models\Credential;
use App\Models\PublicAdministration;
use App\Models\User;
use App\Models\Website;
use Carbon\Carbon;
use CodiceFiscale\Calculator;
use CodiceFiscale\Subject;
use Faker\Factory;
use GuzzleHttp\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Public Administration analytics dashboard controller tests.
 */
class ApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The user.
     *
     * @var User the user
     */
    protected $user;

    /**
     * The non admin user.
     *
     * @var User the user
     */
    protected $userNonAdministrator;

    /**
     * The Kong credential.
     *
     * @var the Kong credential
     */
    private $credential;

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
     * The http client.
     *
     * @var Client
     */
    private $client;

    /**
     * Pre test setup.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->client = new Client(['base_uri' => config('app.url')]);

        $this->publicAdministration = factory(PublicAdministration::class)
            ->state('active')
            ->create();

        $this->user = factory(User::class)->state('active')->create();
        $this->userNonAdministrator = factory(User::class)->create([
            'status' => UserStatus::INVITED,
        ]);

        Bouncer::scope()->onceTo($this->publicAdministration->id, function () {
            $this->userNonAdministrator->assign(UserRole::DELEGATED);
        });

        $this->publicAdministration->users()->sync([
            $this->user->id => ['user_email' => $this->user->email, 'user_status' => UserStatus::ACTIVE],
            $this->userNonAdministrator->id => ['user_email' => $this->userNonAdministrator->email, 'user_status' => UserStatus::INVITED],
        ],
        false);

        $this->website = factory(Website::class)->create([
            'public_administration_id' => $this->publicAdministration->id,
            'status' => WebsiteStatus::ACTIVE,
            'type' => WebsiteType::INSTITUTIONAL,
        ]);

        $this->credential = factory(Credential::class)->create([
            'public_administration_id' => $this->publicAdministration->id,
        ]);

        Bouncer::dontCache();
        $this->userNonAdministrator->registerAnalyticsServiceAccount();

        $this->faker = Factory::create();
    }

    /**
     * Test API request fails due to missing consumer ID header.
     */
    public function testApiErrorNoConsumerId(): void
    {
        $response = $this->json('GET', route('api.websites.show'), [], [
            'X-Consumer-Custom-Id' => '"{\"type\":\"admin\",\"siteId\":[]}"',
        ]);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testApiErrorNoCustomId(): void
    {
        $response = $this->json('GET', route('api.websites.show'), [], [
            'X-Consumer-Id' => 'f8846ee4-031d-4a1b-88d5-08efc0d44eb2',
        ]);

        $this->assertEquals(500, $response->getStatusCode());
    }

    /**
     * Test API request fails as Credential type is not "admin".
     */
    public function testApiErrorAnalyticsType(): void
    {
        $response = $this->json('GET', route('api.websites.show'), [], [
            'X-Consumer-Custom-Id' => '"{\"type\":\"analytics\",\"siteId\":[]}"',
            'X-Consumer-Id' => 'f8846ee4-031d-4a1b-88d5-08efc0d44eb2',
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * Test API website list, should pass.
     */
    public function testWebsiteList(): void
    {
        $response = $this->json('GET', route('api.websites.show'), [], [
            'X-Consumer-Custom-Id' => '{"type":"admin","siteId":[]}',
            'X-Consumer-Id' => $this->credential->consumer_id,
            'Accept' => 'application/json',
        ]);

        $publicAdministration = $this->website->publicAdministration;
        $usersPermissions = Bouncer::scope()->onceTo($publicAdministration->id, function () use ($publicAdministration) {
            return $publicAdministration->users->mapWithKeys(function ($user) {
                $permission = [];
                if ($user->can(UserPermission::READ_ANALYTICS, $this->website)) {
                    array_push($permission, UserPermission::READ_ANALYTICS);
                }
                if ($user->can(UserPermission::MANAGE_ANALYTICS, $this->website)) {
                    array_push($permission, UserPermission::MANAGE_ANALYTICS);
                }

                return [$user->fiscal_number => $permission];
            });
        })->toArray();

        $response
            ->assertStatus(200)
            ->assertJson([[
                'name' => $this->website->name,
                'url' => $this->website->url,
                'slug' => $this->website->slug,
                'status' => $this->website->status->description,
                'type' => $this->website->type->description,
                'permissions' => $usersPermissions,
            ]]);
    }

    /**
     * Test API should pass.
     */
    public function testWebsiteCreate(): void
    {
        $domain_name = 'https://' . $this->faker->domainName;
        $slug = Str::slug($domain_name);
        $name = $this->faker->words(5, true);

        $response = $this->json('POST', route('api.websites.add'), [
            'website_name' => $name,
            'url' => $domain_name,
            'type' => 1,
            'permissions' => [
                $this->userNonAdministrator->fiscal_number => [UserPermission::READ_ANALYTICS],
            ],
        ], [
            'X-Consumer-Custom-Id' => '{"type":"admin","siteId":[]}',
            'X-Consumer-Id' => $this->credential->consumer_id,
            'Accept' => 'application/json',
        ]);

        $location = config('kong-service.api_url') . str_replace('/api/', '/portal/', route('api.websites.read', ['website' => $slug], false));

        $response
            ->assertStatus(201)
            ->assertHeader('Location', $location)
            ->assertJson([
                'name' => $name,
                'url' => $domain_name,
                'slug' => $slug,
                'permissions' => [
                    $this->user->fiscal_number => [],
                    $this->userNonAdministrator->fiscal_number => [],
                ],
            ]);
    }

    /**
     * Read a website from API, should pass.
     *
     * @return void
     */
    public function testWebsiteRead(): void
    {
        $response = $this->json('GET', route('api.websites.read', ['website' => $this->website->slug]), [], [
            'X-Consumer-Custom-Id' => '{"type":"admin","siteId":[]}',
            'X-Consumer-Id' => $this->credential->consumer_id,
            'Accept' => 'application/json',
        ]);

        $publicAdministration = $this->website->publicAdministration;
        $usersPermissions = Bouncer::scope()->onceTo($publicAdministration->id, function () use ($publicAdministration) {
            return $publicAdministration->users->mapWithKeys(function ($user) {
                $permission = [];
                if ($user->can(UserPermission::READ_ANALYTICS, $this->website)) {
                    array_push($permission, UserPermission::READ_ANALYTICS);
                }
                if ($user->can(UserPermission::MANAGE_ANALYTICS, $this->website)) {
                    array_push($permission, UserPermission::MANAGE_ANALYTICS);
                }

                return [$user->fiscal_number => $permission];
            });
        })->toArray();

        $response
            ->assertStatus(200)
            ->assertJson([
                'name' => $this->website->name,
                'url' => $this->website->url,
                'slug' => $this->website->slug,
                'status' => $this->website->status->description,
                'type' => $this->website->type->description,
                'permissions' => $usersPermissions,
            ]);
    }

    /**
     * Edit a website, should pass.
     *
     * @return void
     */
    public function testWebsiteEdit(): void
    {
        $name = $this->faker->words(5, true);
        $domain = 'https://' . $this->faker->domainName;

        $analyticsId = app()->make('analytics-service')->registerSite($name, $domain, $this->publicAdministration->name);

        $websiteToEdit = factory(Website::class)->make([
            'name' => $name,
            'public_administration_id' => $this->publicAdministration->id,
            'status' => WebsiteStatus::ACTIVE,
            'type' => WebsiteType::INSTITUTIONAL_PLAY,
            'analytics_id' => $analyticsId,
        ]);
        $websiteToEdit->save();

        $newDomain = 'https://' . $this->faker->domainName;
        $newSlug = Str::slug($newDomain);
        $newName = $this->faker->words(5, true);

        $response = $this->json('PUT', route('api.websites.update', ['website' => $websiteToEdit]), [
            'website_name' => $newName,
            'url' => $newDomain,
            'type' => 3,
            'slug' => $newSlug,
            'permissions' => [
                $this->userNonAdministrator->fiscal_number => [
                    UserPermission::READ_ANALYTICS,
                    UserPermission::MANAGE_ANALYTICS,
                ],
            ],
        ], [
            'X-Consumer-Custom-Id' => '{"type":"admin","siteId":[]}',
            'X-Consumer-Id' => $this->credential->consumer_id,
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'name' => $newName,
                'url' => $newDomain,
                'slug' => $newSlug,
                'permissions' => [
                    $this->user->fiscal_number => [],
                    $this->userNonAdministrator->fiscal_number => [],
                ],
            ]);
    }

    /*
        Check if the website is active
    */
    public function testWebsiteCheck(): void
    {
        $name = $this->faker->words(5, true);
        $domain = 'https://' . $this->faker->domainName;
        $analyticsId = app()->make('analytics-service')->registerSite($name, $domain, $this->publicAdministration->name);

        $websiteCheck = factory(Website::class)->make([
            'name' => $name,
            'public_administration_id' => $this->publicAdministration->id,
            'status' => WebsiteStatus::PENDING,
            'type' => WebsiteType::INSTITUTIONAL_PLAY,
            'analytics_id' => $analyticsId,
        ]);
        $websiteCheck->save();

        $response = $this->json('GET', route('api.websites.check', ['website' => $websiteCheck]), [], [
            'X-Consumer-Custom-Id' => '{"type":"admin","siteId":[]}',
            'X-Consumer-Id' => $this->credential->consumer_id,
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(303);
    }

    /**
     * Get the matomo javascript snippet, should pass.
     *
     * @return void
     */
    public function testWebsiteSnippet(): void
    {
        $response = $this->json('GET', route('api.websites.snippet.javascript', ['website' => $this->website]), [], [
            'X-Consumer-Custom-Id' => '{"type":"admin","siteId":[]}',
            'X-Consumer-Id' => $this->credential->consumer_id,
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'javascriptSnippet',
            ]);
    }

    /**
     * Test API user list.
     */
    public function testUserList(): void
    {
        $response = $this->json('GET', route('api.users'), [], [
            'X-Consumer-Custom-Id' => '{"type":"admin","siteId":[]}',
            'X-Consumer-Id' => $this->credential->consumer_id,
            'Accept' => 'application/json',
        ]);

        $user = $this->user;

        $status = $user->getStatusforPublicAdministration($this->publicAdministration)->description ?? null;

        $websitePermissions = $this->publicAdministration->websites->mapWithKeys(function ($website) use ($user) {
            $permission = [];
            if ($user->can(UserPermission::READ_ANALYTICS, $website)) {
                array_push($permission, UserPermission::READ_ANALYTICS);
            }
            if ($user->can(UserPermission::MANAGE_ANALYTICS, $website)) {
                array_push($permission, UserPermission::MANAGE_ANALYTICS);
            }

            return [$website->slug => $permission];
        })->toArray();

        $response
            ->assertStatus(200)
            ->assertJson([[
                'first_name' => $user->name,
                'family_name' => $user->family_name,
                'fiscal_number' => $user->fiscal_number,
                'email' => $user->email,
                'status' => $status,
                'permissions' => $websitePermissions,
                'role' => $user->all_role_names,
            ]]);
    }

    /**
     * Create a new user, should pass.
     *
     * @return void
     */
    public function testUserCreate(): void
    {
        $email = $this->faker->unique()->freeEmail;
        $fiscalNumber = (new Calculator(
            new Subject(
                [
                    'name' => $this->faker->firstName,
                    'surname' => $this->faker->lastName,
                    'birthDate' => Carbon::createFromDate(rand(1950, 1990), rand(1, 12), rand(1, 28)),
                    'gender' => rand(0, 1) ? 'F' : 'M',
                    'belfioreCode' => 'H501',
                ]
            )
        ))->calculate();

        $websiteSlug = $this->website->slug;

        $response = $this->json('POST', route('api.users.store'), [
            'email' => $email,
            'fiscal_number' => $fiscalNumber,
            'permissions' => [
                $websiteSlug => [UserPermission::MANAGE_ANALYTICS],
            ],
        ], [
            'X-Consumer-Custom-Id' => '{"type":"admin","siteId":[]}',
            'X-Consumer-Id' => $this->credential->consumer_id,
            'Accept' => 'application/json',
        ]);

        $location = config('kong-service.api_url') . str_replace('/api/', '/portal/', route('api.users.show', ['fn' => $fiscalNumber], false));

        $user = User::findNotSuperAdminByFiscalNumber($fiscalNumber);

        $status = $user->getStatusforPublicAdministration($this->publicAdministration)->description ?? null;
        $role = Bouncer::scope()->onceTo($this->publicAdministration->id, function () use ($user) {
            return $user->all_role_names;
        });

        $response
            ->assertStatus(201)
            ->assertHeader('Location', $location)
            ->assertJson([
                'first_name' => null,
                'family_name' => null,
                'fiscal_number' => $fiscalNumber,
                'email' => $email,
                'status' => $status,
                'permissions' => [
                    $websiteSlug => [UserPermission::READ_ANALYTICS, UserPermission::MANAGE_ANALYTICS],
                ],
                'role' => $role,
            ]);
    }

    /**
     * Get a user from the API.
     *
     * @return void
     */
    public function testUserRead(): void
    {
        $response = $this->json('GET', route('api.users.show', ['fn' => $this->user->fiscal_number]), [], [
            'X-Consumer-Custom-Id' => '{"type":"admin","siteId":[]}',
            'X-Consumer-Id' => $this->credential->consumer_id,
            'Accept' => 'application/json',
        ]);

        $user = $this->user;

        $status = $user->getStatusforPublicAdministration($this->publicAdministration)->description ?? null;

        $websitePermissions = $this->publicAdministration->websites->mapWithKeys(function ($website) use ($user) {
            $permission = [];
            if ($user->can(UserPermission::READ_ANALYTICS, $website)) {
                array_push($permission, UserPermission::READ_ANALYTICS);
            }
            if ($user->can(UserPermission::MANAGE_ANALYTICS, $website)) {
                array_push($permission, UserPermission::MANAGE_ANALYTICS);
            }

            return [$website->slug => $permission];
        })->toArray();

        $response
            ->assertStatus(200)
            ->assertJson([
                'first_name' => $user->name,
                'family_name' => $user->family_name,
                'fiscal_number' => $user->fiscal_number,
                'email' => $user->email,
                'status' => $status,
                'permissions' => $websitePermissions,
                'role' => $user->all_role_names,
            ]);
    }

    /**
     * Edit a user, should pass.
     *
     * @return void
     */
    public function testUserEdit(): void
    {
        $userToEdit = factory(User::class)->make([
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => Date::now(),
        ]);
        $userToEdit->save();
        $this->publicAdministration->users()->sync([$userToEdit->id => ['user_email' => $userToEdit->email, 'user_status' => UserStatus::ACTIVE]]);

        $userToEdit->registerAnalyticsServiceAccount();
        $analyticsId = app()->make('analytics-service')->registerSite($this->website->name . ' [' . $this->website->type . ']', $this->website->url, $this->publicAdministration->name);
        $this->website->analytics_id = $analyticsId;
        $this->website->save();
        app()->make('analytics-service')->setWebsiteAccess($userToEdit->uuid, WebsiteAccessType::WRITE, $this->website->analytics_id);

        $email = $this->faker->unique()->freeEmail;
        $updatedEmail = $this->faker->unique()->freeEmail;

        $response = $this->json('PUT', route('api.users.update', ['fn' => $userToEdit->fiscal_number]), [
            'emailPublicAdministrationUser' => $updatedEmail,
            'email' => $email,
            'permissions' => [
                $this->website->slug => [
                    UserPermission::MANAGE_ANALYTICS,
                    UserPermission::READ_ANALYTICS,
                ],
            ],
        ], [
            'X-Consumer-Custom-Id' => '{"type":"admin","siteId":[]}',
            'X-Consumer-Id' => $this->credential->consumer_id,
            'Accept' => 'application/json',
        ]);

        $status = $userToEdit->getStatusforPublicAdministration($this->publicAdministration)->description ?? null;
        $role = Bouncer::scope()->onceTo($this->publicAdministration->id, function () use ($userToEdit) {
            return $userToEdit->all_role_names;
        });

        $websitePermissions = $this->publicAdministration->websites->mapWithKeys(function ($website) use ($userToEdit) {
            $permission = [];
            if ($userToEdit->can(UserPermission::READ_ANALYTICS, $website)) {
                array_push($permission, UserPermission::READ_ANALYTICS);
            }
            if ($userToEdit->can(UserPermission::MANAGE_ANALYTICS, $website)) {
                array_push($permission, UserPermission::MANAGE_ANALYTICS);
            }

            return [$website->slug => $permission];
        })->toArray();

        $response
            ->assertStatus(200)
            ->assertJson([
                'first_name' => $userToEdit->name,
                'family_name' => $userToEdit->family_name,
                'fiscal_number' => $userToEdit->fiscal_number,
                'email' => $updatedEmail,
                'status' => $status,
                'permissions' => $websitePermissions,
                'role' => $role,
            ]);
    }

    /**
     * Suspend a user, should pass.
     *
     * @return void
     */
    public function testUserSuspend(): void
    {
        $response = $this->json('GET', route('api.users.suspend', ['fn' => $this->user->fiscal_number]), [], [
            'X-Consumer-Custom-Id' => '{"type":"admin","siteId":[]}',
            'X-Consumer-Id' => $this->credential->consumer_id,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
    }
}
