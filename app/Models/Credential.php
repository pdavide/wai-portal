<?php

namespace App\Models;

use BenSampo\Enum\Traits\CastsEnums;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Credential extends Model
{
    use CastsEnums;

    protected $fillable = [
        'public_administration_id',
        'client_name',
        'consumer_id',
    ];

    /**
     * Get the route key for the model.
     *
     * @return string the DB column name to use for route binding
     */
    public function getRouteKeyName(): string
    {
        return 'consumer_id';
    }

    /**
     * Get a credential from a consumer ID.
     *
     * @param string $consumerID The Consumer ID
     *
     * @return Credential|null The Credential
     */
    public static function getCredentialFromConsumerId(string $consumerID): ?Credential
    {
        return Credential::where('consumer_id', $consumerID)->first();
    }

    /**
     * Credentials belong to one public administration.
     *
     * @return BelongsTo
     */
    public function publicAdministration(): BelongsTo
    {
        return $this->belongsTo(PublicAdministration::class);
    }

    /**
     * Accessor for the type attribute of the credential.
     *
     * @return string
     */
    public function getTypeAttribute(): string
    {
        $data = $this->customIdArray($this->consumer_id);

        return $data['type'];
    }

    /**
     * Accessor for the permissions attribute of the credential.
     *
     * @return array
     */
    public function getPermissionsAttribute(): array
    {
        $data = $this->customIdArray($this->consumer_id);

        return is_array($data['siteId'])
            ? $data['siteId']
            : [];
    }

    /**
     * Accessor for the Client ID attribute of the credential.
     *
     * @return string
     */
    public function getClientIdAttribute(): string
    {
        $data = app()->make('kong-client-service')->getClient($this->consumer_id);

        return $data['client_id'];
    }

    /**
     * Accessor for the Client Secret attribute of the credential.
     *
     * @return string
     */
    public function getClientSecretAttribute(): string
    {
        $data = app()->make('kong-client-service')->getClient($this->consumer_id);

        return $data['client_secret'];
    }

    /**
     * Accessor for the Oauth Client ID attribute of the credential.
     *
     * @return string
     */
    public function getOauthClientIdAttribute(): string
    {
        $data = app()->make('kong-client-service')->getClient($this->consumer_id);

        return $data['id'];
    }

    /**
     * Get credential consumer from kong.
     *
     * @param string $consumerId the consumer id
     *
     * @return array|null
     */
    private function customIdArray(string $consumerId): ?array
    {
        $consumer = app()->make('kong-client-service')->getConsumer($consumerId);

        return json_decode($consumer['custom_id'], true);
    }
}
