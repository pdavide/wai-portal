<?php

namespace App\Transformers;

use App\Enums\CredentialType;
use App\Enums\UserPermission;
use App\Models\Credential;
use League\Fractal\TransformerAbstract;

/**
 * Credential transformer.
 */
class CredentialsTransformer extends TransformerAbstract
{
    /**
     * Transform the api credentials for datatable.
     *
     * @param credentials $credentials
     *
     * @return array the response
     */
    public function transform(Credential $credential): array
    {
        $authUser = auth()->user();

        $data = [
            'client_name' => [
                'display' => implode('', [
                    '<span>',
                    '<strong>' . e($credential->client_name) . '</strong>',
                    '</span>',
                ]),
                'raw' => e($credential->client_name),
            ],
            'type' => CredentialType::getDescription($credential->type),
            'added_at' => $credential->created_at->format('d/m/Y'),
            'icons' => [],
            'buttons' => [],
        ];

        $data['buttons'][] = [
            'link' => route('api-credentials.show', ['credential' => $credential->consumer_id]),
            'color' => 'outline-primary',
            'label' => __('dettagli'),
        ];

        if ($authUser->can(UserPermission::MANAGE_WEBSITES)) {
            $data['icons'][] = [
                'icon' => 'it-pencil',
                'link' => route('api-credentials.edit', ['credential' => $credential->consumer_id]),
                'color' => 'primary',
                'title' => __('modifica'),
            ];
        }

        $data['buttons'][] = [
                'link' => route('api-credentials.delete', ['credential' => $credential->consumer_id]),
                'label' => __('elimina'),
                'color' => 'danger',
                'dataAttributes' => [
                    'credentialName' => e($credential->client_name),
                    'type' => 'credentialDelete',
                    'ajax' => true,
                ],
            ];

        return $data;
    }
}
