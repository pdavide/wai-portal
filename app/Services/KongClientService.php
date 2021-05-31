<?php

namespace App\Services;

use App\Exceptions\ApiGatewayServiceException;
use App\Exceptions\InvalidCredentialException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Kong Services Api.
 */
class KongClientService
{
    /**
     * Local service URL.
     *
     * @var string the local URL
     */
    protected $serviceBaseUri;

    /**
     * Default constructor.
     */
    public function __construct()
    {
        $this->serviceBaseUri = config('kong-service.admin_api_url');
    }

    /**
     * Return a consumer from its ID.
     *
     * @param string $idConsumer The consumer ID
     *
     * @return array|null The consumer
     */
    public function getConsumer(string $idConsumer): ?array
    {
        $consumer = Cache::get('kong:consumer:' . $idConsumer);

        if (null !== $consumer) {
            $consumer = json_decode($consumer);
            $consumer = (array) $consumer;

            return $consumer;
        }

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $consumer = $this->apiCall($headers, [], 'GET', '/consumers/' . $idConsumer);

        Cache::put('kong:consumer:' . $idConsumer, json_encode($consumer));

        return $consumer;
    }

    /**
     * Get a Client from the consumer id.
     *
     * @param string $idConsumer The consumer ID
     *
     * @return array|null The Client
     */
    public function getClient(string $idConsumer): ?array
    {
        $client = Cache::get('kong:client:' . $idConsumer);

        if (null !== $client) {
            $client = json_decode($client);

            if (null === $client && !is_array($client)) {
                throw new InvalidCredentialException('OAuth2 credentials not found');
            }

            return (array) $client;
        }

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $clients = $this->apiCall($headers, [], 'GET', '/consumers/' . $idConsumer . '/oauth2');
        $clients = $clients['data'];

        if (null === $clients && !is_array($clients)) {
            throw new InvalidCredentialException('OAuth2 credentials not found');
        }

        $client = $clients[0];

        Cache::put('kong:client:' . $idConsumer, json_encode($client));

        return $client;
    }

    /**
     * Create a new consumer.
     *
     * @param string $username The consumer's username
     * @param string $customId The consumer's custom id
     *
     * @return array The new consumer
     */
    public function makeConsumer(string $username, string $customId): array
    {
        $data = [
            'json' => [
                'username' => $username,
                'custom_id' => $customId,
            ],
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];

        $response = $this->apiCall($data['headers'], $data['json'], 'POST', '/consumers');

        Cache::put('kong:consumer:' . $response['id'], json_encode($response));

        return $this->makeClient($response['username'], $response['id']);
    }

    /**
     * Create a new client.
     *
     * @param string $name The client name
     * @param string $idConsumer The consumer id
     *
     * @return array The client
     */
    public function makeClient(string $name, string $idConsumer): array
    {
        $data = [
            'form_params' => [
                'name' => $name . '-oauth2',
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        $response = $this->apiCall($data['headers'], $data['form_params'], 'POST', '/consumers/' . $name . '/oauth2', true);

        Cache::put('kong:client:' . $idConsumer, json_encode($response));

        return $response;
    }

    /**
     * Regenerate a client secret.
     *
     * @param string $name The client's name
     * @param string $idConsumer The consumer's id
     * @param string $idClient The client's id
     *
     * @return array The client
     */
    public function regenerateSecret(string $name, string $idConsumer, string $idClient): array
    {
        $data = [
            'form_params' => [
                'name' => $name . '-oauth2',
                'client_id' => $idClient,
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        $response = $this->apiCall($data['headers'], $data['form_params'], 'PUT', '/consumers/' . $name . '/oauth2/' . $idClient, true);

        Cache::put('kong:client:' . $idConsumer, json_encode($response));

        return $response;
    }

    /**
     * Update the client information.
     *
     * @param string $idConsumer The consumer id
     * @param array $newData The new client data
     *
     * @return array The client
     */
    public function updateClient(string $idConsumer, array $newData): array
    {
        $data = [
            'json' => $newData,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];

        $consumer = $this->apiCall($data['headers'], $data['json'], 'PUT', '/consumers/' . $idConsumer);
        Cache::put('kong:consumer:' . $idConsumer, json_encode($consumer));

        return $consumer;
    }

    /**
     * Delete a consumer.
     *
     * @param string $idConsumer The consumer id
     *
     * @return bool Wether the consumer has been deleted
     */
    public function deleteConsumer(string $idConsumer): bool
    {
        $data = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];

        $consumer = $this->apiCall($data['headers'], [], 'DELETE', '/consumers/' . $idConsumer);

        return true;
    }

    /**
     * Get all the tokens beloging to a credential.
     *
     * @return array|null The tokens or null
     */
    public function getTokensList(): ?array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        return $this->apiCall($headers, [], 'GET', '/oauth2_tokens/');
    }

    /**
     * Invalidate a credential's token.
     *
     * @param string $tokenId The token ID
     *
     * @return void
     */
    public function invalidateToken(string $tokenId)
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $this->apiCall($headers, [], 'DELETE', '/oauth2_tokens/' . $tokenId);
    }

    /**
     * Api Rest calls to kong.
     *
     * @param array $headers The headers
     * @param array $body The body
     * @param string $method The method
     * @param string $path The path to form the url
     * @param bool $isForm Wether is a "application/x-www-form-urlencoded" request
     *
     * @return array|null The data or null
     */
    protected function apiCall(array $headers = [], array $body = [], string $method = 'GET', string $path = '/', bool $isForm = false): ?array
    {
        $method = strtolower($method);

        try {
            if ($isForm) {
                $res = Http::withHeaders($headers)
                    ->asForm()
                    ->{$method}(
                        $this->serviceBaseUri . $path,
                        $body
                    );
            } else {
                $res = Http::withHeaders($headers)
                    ->{$method}(
                        $this->serviceBaseUri . $path,
                        $body
                    );
            }
        } catch (RequestException $exception) {
            throw new ApiGatewayServiceException($exception->getMessage());
        }

        return $res->json();
    }
}
