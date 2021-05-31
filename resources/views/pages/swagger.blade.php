@extends('layouts.page', ['graphicBackground' => true])

@section('title', 'API')

@section('content')
<div class="row">
    <div class="col-lg-12 d-flex">
        @component('layouts.components.box')
        @can(UserPermission::MANAGE_WEBSITES)
            <div class="row">
                @unlessenv ('production')
                <div class="col-md-6 col-12">
                    @if ($hascredentials)
                    <div class="bootstrap-select-wrapper">
                        <label>
                            {!! __('Seleziona una credenziale :admin_credential da usare per le chiamate API di prova.', [
                                'admin_credential' => '<em>' . CredentialType::getDescription(CredentialType::ADMIN) . '</em>'
                            ]) !!}
                        </label>
                        <select title="{{ __('Seleziona una credenziale') }}" class="form-select" id="select-credential">
                            @foreach ($credentialsList as $index => $credential)
                                <option value="{{ $credential->consumer_id }}" data-client-id="{{ $credential->client_id }}" data-client-secret="{{ $credential->client_secret }}">
                                    {{ $credential->client_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @else
                        <p>
                            {!! __('Se vuoi provare le API è necessario prima aggiungere una credenziale di tipologia :admin_credential.', [
                                'admin_credential' => '<strong>' . CredentialType::getDescription(CredentialType::ADMIN) . '</strong>'
                            ]) !!}
                        </p>
                    @endif
                </div>
                @endunless
                <div class="col-md-6 col-12 text-right ml-auto">
                    @component('layouts.components.link_button', [
                        'link' => $credentials,
                        'size' => 'lg',
                        'icon' => 'it-key'
                    ])
                    {{ __('Gestione delle credenziali API') }}
                    @endcomponent
                </div>
            </div>
        @endcan
        <div id="swagger-ui" data-environment={{ $currentEnvironment }}></div>
        @endcomponent
    </div>
</div>
@endsection
