@extends('layouts.page', ['graphicBackground' => true])

@section('title', 'Credenziali API')
@section('title-description')
    {!! __('Le credenziali si riferiscono al :client_credentials_flow', [
        'client_credentials_flow' => '<a class="external-link" target="_blank" href="https://oauth.net/2/grant-types/client-credentials/">' . __('flusso "Client credentials" OAuth2') . '</a>'
    ]) !!}
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12 d-flex">
        @component('layouts.components.box')
        @if (session()->has('tenant_id') || $authUser->can(UserPermission::ACCESS_ADMIN_AREA))
            @unless ($authUser->cannot(UserPermission::MANAGE_WEBSITES))
                @include('partials.datatable')
                <div class="show-when-active mt-4 text-center text-sm-left">
                    @component('layouts.components.link_button', [
                        'icon' => 'it-plus',
                        'link' => $newCredentialUrl,
                        'size' => 'lg',
                    ])
                    {{ __('aggiungi credenziale') }}
                    @endcomponent
                </div>
            @else
                <div class="alert alert-warning" role="alert">
                    {{ __('È necessario attivare almeno un sito web per creare nuove credenziali.') }}
                    <br>
                    {!! __('Vai alla :link_to_websites.', [
                        'link_to_websites' => '<a href="' . route('websites.index') . '">' . __('gestione dei siti web') . '</a>'
                    ]) !!}
                </div>
            @endunless
        @endcomponent
        @endif
    </div>
</div>
@endsection
