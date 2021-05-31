@extends('layouts.page', ['graphicBackground' => true])

@section('title', $website->name)

@section('content')
    <div class="row">
        <div class="col-lg-8 d-flex">
            @component('layouts.components.box')
            <h4 class="text-uppercase m-0">{{ __('informazioni') }}</h4>
            <div class="mt-5 pt-5">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <input type="text" class="form-control-plaintext" id="name" value="{{ $website->name }}" readonly>
                        <label for="name">{{ ucfirst(__('nome del sito')) }}</label>
                    </div>
                    <div class="form-group col-md-6">
                        <input type="text" class="form-control-plaintext" id="url" value="{{ $website->url }}" readonly>
                        <label for="url">{{ __('URL') }}</label>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <input type="text" class="form-control-plaintext" id="type" value="{{ $website->type->description }}" readonly>
                        <label for="type">{{ ucfirst(__('tipologia')) }}</label>
                    </div>
                    <div class="form-group col-md-6">
                        <input type="text" class="form-control-plaintext" id="created_at" value="{{ $website->created_at->format('d/m/Y') }}" readonly>
                        <label for="created_at">{{ __('Aggiunto il') }}</label>
                    </div>
                </div>
                @isset($website->updated_at)
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <input type="text" class="form-control-plaintext" id="updated_at" value="{{ $website->updated_at->format('d/m/Y') }}" readonly>
                        <label for="updated_at">{{ __('Aggiornato il') }}</label>
                    </div>
                </div>
                @endif
                @can(UserPermission::MANAGE_WEBSITES)
                @component('layouts.components.link_button', [
                    'icon' => 'it-pencil',
                    'link' => $websiteEditUrl,
                    'size' => 'lg',
                ])
                {{ __('modifica') }}
                @endcomponent
                @endcan
            </div>
            @endcomponent
        </div>
        <div class="col-lg-4 d-flex">
            @component('layouts.components.box', ['classes' => 'd-flex flex-column justify-content-between'])
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="text-uppercase m-0">{{ __('stato') }}</h4>
                <span class="badge px-3 py-2 website-status {{ strtolower($website->status->key) }}">
                    {{ strtoupper($website->status->description) }}
                </span>
            </div>
            <div class="box-section lightgrey-bg-a1 my-4">
                <p>{{ WebsiteStatus::getLongDescription($website->status) }}</p>
                @if ($website->status->is(WebsiteStatus::PENDING))
                @if ($authUser->can(UserPermission::MANAGE_WEBSITES) || ($userPublicAdministrationStatus && $userPublicAdministrationStatus->is(UserStatus::PENDING)))
                <h5 class="section-header">{{ __('attivazione') }}</h5>
                <p>
                    {{ __('Per impostare il tracciamento, usa il codice che trovi in questa pagina.') }}
                </p>
                <p class="text-serif">
                    {{ __("Hai bisogno di aiuto con l'attivazione?") }} <a href="{{ route('faq') }}">{{ __('Consulta le FAQ') }}</a>
                </p>
                @endif
                @elseif (!$website->type->is(WebsiteType::INSTITUTIONAL))
                @can(UserPermission::MANAGE_WEBSITES)
                @if ($website->status->is(WebsiteStatus::ACTIVE))
                <h5 class="section-header">{{ __('archiviazione') }}</h5>
                <p>
                    {{ __('Se non vuoi più tracciare il traffico di questo sito puoi archiviarlo.') }}
                </p>
                <a role="button" class="btn btn-sm btn-outline-secondary disabled"
                    href="{{ $websiteArchiveUrl }}"
                    data-type="websiteArchiveUnarchive"
                    data-website-name="{{ $website->name }}"
                    data-current-status-description="{{ $website->status->description }}"
                    data-current-status="{{ $website->status->key }}"
                    aria-disabled="true">
                    {{ ucfirst(__('archivia')) }}
                </a>
                @else
                <h5 class="section-header">{{ __('ripristino') }}</h5>
                <p>
                    {{ __('Se vuoi di nuovo tracciare il traffico di questo sito puoi ripristinarlo.') }}
                </p>
                <a role="button" class="btn btn-sm btn-outline-secondary disabled"
                    href="{{ $websiteUnarchiveUrl }}"
                    data-type="websiteArchiveUnarchive"
                    data-website-name="{{ $website->name }}"
                    data-current-status-description="{{ $website->status->description }}"
                    data-current-status="{{ $website->status->key }}"
                    aria-disabled="true">
                    {{ ucfirst(__('ripristina')) }}
                </a>
                @endif
                @endcan
                @else
                <h5 class="section-header">{{ __('Sito istituzionale') }}</h5>
                <p>
                    {{ __('Questo è il sito istituzionale della tua Pubblica Amministrazione.') }}
                </p>
                @can(UserPermission::MANAGE_WEBSITES)
                <p>
                    {{ __('Non è possibile modificare lo stato.') }}
                </p>
                @endcan
                @endif
            </div>
            <div>
                <p class="text-serif">
                    {{ __('Hai dubbi sul significato dello stato del sito?') }}
                    <a href="{{ route('faq') }}">{{ __('Consulta le FAQ') }}</a>
                </p>
            </div>
            @endcomponent
        </div>
    </div>
    @if ($authUser->can(UserPermission::MANAGE_WEBSITES) || ($userPublicAdministrationStatus && $userPublicAdministrationStatus->is(UserStatus::PENDING)))
    @component('layouts.components.box', ['classes' => 'mt-0 full-width'])
    <div class="javascript-snippet-container blank">
        <div class="collapse-header box-section" id="javascript-snippet-header">
            <button class="px-0" data-toggle="collapse" data-target="#javascript-snippet-collapse" aria-expanded="false" aria-controls="javascript-snippet-collapse">
                <h4 class="text-uppercase m-0 text-black">{{ __('codice di tracciamento javascript') }}</h4>
            </button>
        </div>
        <div id="javascript-snippet-collapse" class="collapse" role="tabpanel" aria-labelledby="javascript-snippet-header">
            <div class="box-section lightgrey-bg-a1 my-4 mx-0 px-5">
                <div class="collapse-body">
                    <div id="javascript-snippet" class="lightgrey-bg-a1" data-href="{{ $javascriptSnippetUrl }}">
                        <pre><code></code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @cannot(UserPermission::ACCESS_ADMIN_AREA)
    <div class="row box-section">
        <div class="@if($forceActivationButtonVisible && $website->status->is(WebsiteStatus::PENDING)) col-md-4 @else col-md-5 @endif">
            @if ($website->status->is(WebsiteStatus::PENDING))
            <h5 class="section-header">{{ __('verifica tracciamento') }}</h5>
            <p>
                {{ __('Se hai già inserito il codice di tracciamento nel sito') }}
                <strong>{{ $website->name }}</strong>
                {{ __('puoi eseguire adesso la verifica e attivare il sito.') }}
            </p>
            <p class="text-center">
                <a role="button" class="btn btn-sm btn-outline-secondary text-center disabled"
                    href="{{ $websiteTrackingCheckUrl }}"
                    data-type="checkTracking"
                    data-website-name="{{ $website->name }}"
                    aria-disabled="true">
                    {{ __('Verifica adesso') }}
                </a>
            </p>

            @if ($forceActivationButtonVisible)
            </div>
            <div class="col-md-4" >
            <h5 class="section-header">{{ __('forza attivazione') }}</h5>
            <p>
                {{ __("Questa è una pubblica amministrazione personalizzata, puoi saltare la verifica e forzare l'attivazione del sito.") }}
            </p>
            <p class="text-center">
                <a role="button" class="btn btn-sm btn-outline-secondary text-center disabled"
                    href="{{ $websiteActivateForceUrl }}"
                    data-type="checkTracking"
                    data-website-name="{{ $website->name }}"
                    aria-disabled="true">
                    {{ __('Attivazione forzata') }}
                </a>
            </p>

            @endif

            @elseif ($website->status->is(WebsiteStatus::ACTIVE))
            <h5 class="section-header">{{ __('tracciamento attivo') }}</h5>
            <p>
                {{ __('Il sito') }}
                <strong>{{ $website->name }}</strong>
                {{ __('sta già tracciando il traffico! 🎉') }}
                {!! __('Consulta i dati analytics e prendi :data_driven_decisions per la tua PA.', ['data_driven_decisions' => '<em>' . __('decisioni basate sui dati') . '</em>']) !!}
            </p>
            <p class="text-center">
                <a role="button" class="btn btn-sm btn-icon btn-outline-primary"
                    href="{{ route('analytics.service.login', ['websiteAnalyticsId' => $website->analytics_id]) }}">
                    {{ __('Vai alla dashboard') }}
                    <svg class="icon icon-primary ml-2 align-middle">
                        <use xlink:href="{{ asset('svg/sprite.svg#it-arrow-right') }}"></use>
                    </svg>
                </a>
            </p>
            @else
            @endif
        </div>
        <div class="@if($forceActivationButtonVisible && $website->status->is(WebsiteStatus::PENDING)) col-md-4 @else col-md-5 offset-md-2 @endif">
            <h5 class="section-header">{{ __('opzioni avanzate') }}</h5>
            <p class="text-serif">
                {{ __('Se vuoi personalizzare il codice di tracciamento per attivare opzioni avanzate') }},
                <a href="{{ route('faq') }}"> {{ __('consulta la nostra guida') }}</a>.
            </p>
        </div>
    </div>
    @endcannot
    @endcomponent
    @endif

    @if ($website->status->is(WebsiteStatus::ACTIVE))
        {{-- Momentaneo - decidere se spostarlo --}}
        @component('layouts.components.box')
        <div>
            <h4>{{__('Widgets')}}</h4>
            <p>
                {{ __('Puoi visitare la pagina dei ')}} 
                <strong> {{ __("Widgets")}} </strong>
                {{ __('che contiene la lista, l\'anteprima e il codice html dei widget da copiare e inserire nel sito dell\'amministrazione') }}
            </p>
            <a role="button" class="btn btn-sm btn-icon btn-outline-primary"
                href="{{ route('websites.show.widgets', ['website' => $website]) }}">
                {{ __('Vai alla pagina dei Widgets') }}
                <svg class="icon icon-primary ml-2 align-middle">
                    <use xlink:href="{{ asset('svg/sprite.svg#it-arrow-right') }}"></use>
                </svg>
            </a>
        </div>
        @endcomponent
    @endif
    
    @can(UserPermission::MANAGE_WEBSITES)
    @component('layouts.components.box', ['classes' => 'mt-0'])
    <h4 class="text-uppercase mb-5">{{ __('Permessi degli utenti') }}</h4>
    @include('partials.datatable')
    <div class="mt-4 text-center text-sm-left">
    @component('layouts.components.link_button', [
        'icon' => 'it-pencil',
        'link' => $websiteEditUrl,
        'size' => 'lg',
    ])
    {{ __('modifica') }}
    @endcomponent
    </div>
    @endcomponent
    @endcan
    </div>
@endsection
