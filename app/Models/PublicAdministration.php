<?php

namespace App\Models;

use App\Enums\PublicAdministrationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Traits\HasAnalyticsDashboard;
use App\Traits\SendsNotificationsToPublicAdministrationAdmin;
use App\Traits\SendsNotificationsToPublicAdministrationRTD;
use BenSampo\Enum\Traits\CastsEnums;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Silber\Bouncer\BouncerFacade as Bouncer;

/**
 * Public Administration model.
 */
class PublicAdministration extends Model
{
    use CastsEnums;
    use SoftDeletes;
    use Notifiable;
    use HasAnalyticsDashboard;
    use SendsNotificationsToPublicAdministrationAdmin;
    use SendsNotificationsToPublicAdministrationRTD;

    /**
     * Active public administrations count cache key.
     *
     * @var string the key
     */
    public const PUBLIC_ADMINISTRATION_COUNT_KEY = 'paCount';

    /**
     * The attributes that are mass assignable.
     *
     * @var array Mass assignable attributes
     */
    protected $fillable = [
        'ipa_code',
        'name',
        'pec',
        'rtd_name',
        'rtd_mail',
        'rtd_pec',
        'city',
        'county',
        'region',
        'type',
        'status',
        'rollup_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'status' => 'integer',
    ];

    /**
     * The attributes that should be cast to enums classes.
     *
     * @var array enum casted attributes
     */
    protected $enumCasts = [
        'status' => PublicAdministrationStatus::class,
    ];

    /**
     * Find a Public Administration instance by IPA code.
     *
     * @param string $ipa_code IPA code
     *
     * @return PublicAdministration|null The Public Administration found or null if not found
     */
    public static function findByIpaCode(string $ipa_code): ?PublicAdministration
    {
        return PublicAdministration::where('ipa_code', $ipa_code)->first();
    }

    /**
     * Find a deleted Public Administration instance by IPA code.
     *
     * @param string $ipa_code IPA code
     *
     * @return PublicAdministration|null The Public Administration found or null if not found
     */
    public static function findTrashedByIpaCode(string $ipa_code): ?PublicAdministration
    {
        return PublicAdministration::onlyTrashed()->where('ipa_code', $ipa_code)->first();
    }

    /**
     * Get active public administrations counter.
     *
     * @return int the count
     */
    public static function getCount(): int
    {
        return Cache::rememberForever(self::PUBLIC_ADMINISTRATION_COUNT_KEY, function () {
            return PublicAdministration::all('ipa_code')->count();
        });
    }

    /**
     * Get the route key for the model.
     *
     * @return string The DB column name to use for route binding
     */
    public function getRouteKeyName(): string
    {
        return 'ipa_code';
    }

    /**
     * The users belonging to this Public Administration.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany the relation to the users belonging to this Public Administration
     *
     * @see \App\Models\User
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('user_status')->withPivot('user_email');
    }

    /**
     * The websites of this Public Administration.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany the relation to the websites of this Public Administration
     *
     * @see \App\Models\Website
     */
    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    /**
     * Return name and IPA code of this public administration in printable format.
     *
     * @return string the printable public administration representation
     */
    public function getInfoAttribute(): string
    {
        return '"' . $this->name . '" [' . $this->ipa_code . ']';
    }

    /**
     * Get the administrators users of this public administration.
     *
     * @return Collection the users list
     */
    public function getAdministrators(): Collection
    {
        if ($this->status->is(PublicAdministrationStatus::PENDING)) {
            return $this->users()->where('user_status', UserStatus::PENDING)->get();
        }

        return Bouncer::scope()->onceTo($this->id, function () {
            return User::whereIs(UserRole::ADMIN)->get();
        });
    }

    /**
     * Get the active administrators users of this public administration.
     *
     * @return Collection the users list
     */
    public function getActiveAdministrators(): Collection
    {
        return $this->getAdministrators()->filter(function ($administrator) {
            return $administrator->status->is(UserStatus::ACTIVE);
        });
    }

    /**
     * Get all the non administrators users of this public administration.
     *
     * @return Collection the users list
     */
    public function getNonAdministrators(): Collection
    {
        if ($this->status->is(PublicAdministrationStatus::PENDING)) {
            return Collection::make();
        }

        return Bouncer::scope()->onceTo($this->id, function () {
            return User::whereIs(UserRole::DELEGATED)->get();
        });
    }

    /**
     * One Public Administration has many credentials.
     *
     * @return HasMany
     */
    public function credentials()
    {
        return $this->hasMany(Credential::class);
    }
}
