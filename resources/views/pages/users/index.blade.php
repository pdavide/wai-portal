@extends('layouts.page', ['graphicBackground' => true])

@section('title', __('Utenti'))

@section('content')
    @component('layouts.components.box', ['classes' => 'rounded'])
    @include('partials.datatable')
    @if ($authUser->can(UserPermission::MANAGE_USERS))
        <div class="mt-4 text-center text-sm-left">
        @component('layouts.components.link_button', [
            'icon' => 'it-plus',
            'link' => $userCreateUrl,
            'size' => 'lg',
        ])
        {{ __('aggiungi utente') }}
        @endcomponent
        </div>
    @elseif ($authUser->status->is(UserStatus::PENDING))
        <div class="alert alert-info mt-5" role="alert">
            {{ __("Nuovi :elements potranno essere aggiunti solo dopo l'attivazione del sito istituzionale.", [
                'elements' => __('utenti')
            ]) }}
        </div>
    @endif
    @endcomponent
@endsection
