@extends('layouts.page', ['graphicBackground' => true])

@section('title', 'Anteprima widget')

@section('content')
    <div class="row" id="widgets-preview-box" data-url="{{ $widgetsBaseUrl }}">
        <div class="col-lg-12 d-flex">
            @component('layouts.components.box')
                <div class="card-wrapper card-space">
                    <div class="card card-bg no-after">
                        <div class="card-body">
                            <h5 class="card-title">{{ __('Anteprima') }}</h5>
                            <p id="widget-subtitle-1"></p>
                            <div id="widget-preview" class="mt-4">
                                {{ __('Nessun widget selezionato') }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-wrapper card-space mb-5">
                    <div class="card card-bg no-after">
                        <div class="card-body">
                            <h5 class="card-title">{{ __('Codice HTML del widget') }}</h5>
                            <p id="widget-subtitle-2"></p>
                            <code id="widget-code" class="mt-4">
                                {{ __('Nessun widget selezionato') }}
                            </code>
                            <div class="it-card-footer">
                                <div class="alert alert-info" role="alert">
                                    {{ __('Questo codice è utilizzabile esclusivamente nelle pagine web') }}
                                    @if (count($allowedFqdns) > 1)
                                        {{ __('sotto i seguenti domini:') }}
                                        <ul>
                                        @foreach ($allowedFqdns as $allowedFqdn)
                                            <li><code>{{ $allowedFqdn }}</code></li>
                                        @endforeach
                                        </ul>
                                    @else
                                        {!! __('sotto il dominio :fqdn.', [
                                            'fqdn' => '<code>' . head($allowedFqdns) . '</code>'
                                        ]) !!}
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <h3>{{ __('Widget disponibili') }}</h3>
                <p>{{ __("Seleziona un widget per vederne l'anteprima e ottenere il codice HTML da inserire nel sito web.") }}</p>
                <div class="row">
                    @foreach ($widgets as $key => $widget)
                    @if (is_array($allowedWidgets) && in_array($widget["uniqueId"], $allowedWidgets))
                        <div class="col-md-4">
                            <div class="btn btn-block border rounded mb-4 text-center pointer" id="widget-{{ $key }}"
                                data-type="widget-select" data-attribute="back-to-top">
                                <span style="display: none;" id="widget-json-{{ $key }}"
                                    site="{{ $idSite }}">{{ json_encode($widget) }}</span>
                                <span class="align-middle" id="widget-child-text-{{ $key }}">{{ $widget['name'] }}</span>
                            </div>
                        </div>
                    @endif
                    @endforeach
                </div>
            @endcomponent
        </div>
    </div>
@endsection
