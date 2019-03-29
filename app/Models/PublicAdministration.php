<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PublicAdministration extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ipa_code',
        'name',
        'pec_address',
        'city',
        'county',
        'region',
        'type',
        'status',
    ];

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public static function findByIPACode(string $ipa_code): ?PublicAdministration
    {
        return PublicAdministration::where('ipa_code', $ipa_code)->first();
    }

    /**
     * Find a PublicAdministration instance by IPA code.
     *
     * @param string IPA code
     *
     * @return PublicAdministration|null the PublicAdministration found or null if not found
     */
    public static function findTrashedByIPACode(string $ipa_code): ?PublicAdministration
    {
        return PublicAdministration::onlyTrashed()->where('ipa_code', $ipa_code)->first();
    }
    public function getRouteKeyName(): string
    {
        return 'ipa_code';
    }

    /**
     * The users belonging to this Public Administration.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * The owner if this verification token.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function websites()
    {
        return $this->hasMany(Website::class);
    }
}
