@extends('layouts.default')

@section('title', __('ui.pages.users.add.title'))

@section('content')
    <form class="Form Form--spaced u-text-r-xs" method="post" action="{{ route('users-store', [], false) }}">
        @csrf
        @if ($errors->isEmpty())
            <div class="Prose Alert Alert--info">
                <p class="u-text-p">Tutti i campi sono richiesti salvo dove espressamente indicato.</p>
            </div>
        @else
            <div class="Alert Alert--error Alert--withIcon u-margin-r-bottom" role="alert">
                <p class="u-text-p">
                    È necessario correggere alcuni errori prima di poter inviare il modulo.
                    {{-- //TODO: put message in lang file --}}
                </p>
            </div>
        @endif
        <fieldset class="Form-fieldset">
            <legend class="Form-legend">
                Informazioni sul nuovo utente{{-- //TODO: put message in lang file --}}
            </legend>
            <div class="Form-field {{ $errors->has('email') ? 'is-invalid' : '' }}">
                @if ($errors->has('email'))
                <div class="Alert Alert--error Alert--withBg u-padding-r-top u-padding-r-bottom u-padding-r-right">
                    <p class="u-text-p u-padding-r-bottom">{{ $errors->first('email') }}</p>
                @endif
                    <label class="Form-label is-required" for="email">
                        Indirizzo email istituzionale{{-- //TODO: put message in lang file --}}
                    </label>
                    <input class="Form-input" id="email" name="email" type="email" aria-required="true" value="{{ old('email') }}" required>
                    <p class="Form-message">
                        Inserisci la mail di lavoro fornita dalla tua PA (es. nome.cognome@agid.gov.it).{{-- //TODO: put message in lang file --}}
                    </p>
                @if ($errors->has('email'))
                </div>
                @endif
            </div>
            <div class="Form-field {{ $errors->has('fiscalNumber') ? 'is-invalid' : '' }}">
                @if ($errors->has('fiscalNumber'))
                <div class="Alert Alert--error Alert--withBg u-padding-r-top u-padding-r-bottom u-padding-r-right">
                    <p class="u-text-p u-padding-r-bottom">{{ $errors->first('fiscalNumber') }}</p>
                @endif
                    <label class="Form-label is-required" for="fiscalNumber">
                        Codice fiscale{{-- //TODO: put message in lang file --}}
                    </label>
                    <input class="Form-input" id="fiscalNumber" name="fiscalNumber" aria-required="true" value="{{ old('fiscalNumber') }}" required>
                @if ($errors->has('fiscalNumber'))
                </div>
                @endif
            </div>
            <div class="Form-field Form-field--choose {{ $errors->has('isAdmin') ? 'is-invalid' : '' }}">
                @if ($errors->has('isAdmin'))
                <div class="Alert Alert--error Alert--withBg u-padding-r-top u-padding-r-bottom u-padding-r-right">
                    <p class="u-text-p u-padding-r-bottom">{{ $errors->first('isAdmin') }}</p>
                @endif
                    <legend class="Form-legend">Ruolo</legend>
                    <label class="Form-label Form-label--block" for="isAdmin">
                        <input type="checkbox" class="Form-input" name="isAdmin" id="isAdmin" value="1" {{ old('isAdmin') ? 'checked' : '' }}>
                        <span class="Form-fieldIcon" role="presentation"></span>Amministratore
                    </label>
                @if ($errors->has('isAdmin'))
                </div>
                @endif
            </div>
            <div class="Form-field {{ $errors->has('websitesPermissions') ? 'is-invalid' : '' }}">
                @if ($errors->has('websitesPermissions'))
                <div class="Alert Alert--error Alert--withBg u-padding-r-top u-padding-r-bottom u-padding-r-right">
                    <p class="u-text-p u-padding-r-bottom">{{ $errors->first('websitesPermissions') }}</p>
                @endif
                    <label class="Form-label is-required" for="websitesPermissions">
                        Permessi{{-- //TODO: put message in lang file --}}
                    </label>
                    @include('partials.datatable')
                @if ($errors->has('websitesPermissions'))
                </div>
                @endif
            </div>
        </fieldset>
        <div class="Form-field Grid-cell u-textRight">
            <button type="submit" class="Button Button--default u-text-xs">
                Invia{{-- //TODO: put message in lang file --}}
            </button>
        </div>
    </form>
@endsection
