@extends('layouts.page', ['graphicBackground' => true])

@section('title', $credential->client_name)

@section('content')
<div class="row">
    <div class="col-lg-12 d-flex">
        @component('layouts.components.box')
            <h4 class="text-uppercase m-0">{{ __('Informazioni credenziale') }}</h4>
            <div class="mt-5 pt-5">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <input
                        type="text"
                        class="form-control-plaintext"
                        id="name"
                        value="{{ $credential->client_name ?? __('Non ancora disponibile') }}"
                        readonly
                        >
                        <label for="name">{{ __('Nome') }}</label>
                    </div>
                    <div class="form-group col-md-6">
                        <input
                        type="text"
                        class="form-control-plaintext"
                        id="id"
                        value="{{ $credential->client_id ?? __('Non ancora disponibile') }}"
                        readonly
                        >
                        <label for="id">{{ __('client_id') }}</label>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <p>
                            {{__('Il ')}}
                            <strong>{{__('client_secret ')}} </strong>
                            {{__('necessario al flusso')}} 
                            <strong>{{__('OAuth2 ')}}</strong> 
                            {{__('ti è stato fornito durante la creazione della credenziale.')}}
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p>{{__('Se necessario poi rigenerare il tuo client_id e client_secret')}}</p>
                        @component('layouts.components.link_button', [
                            'link' => $credentialRegenerate,
                            'size' => 'lg',
                        ])
                        {{ ucfirst(__('Rigenera la credenziale')) }}
                        @endcomponent
                    </div>
                </div>
            </div>
            <div class="mt-4 text-center text-sm-left">
                @component('layouts.components.link_button', [
                    'icon' => 'it-pencil',
                    'link' => $credentialEditUrl,
                    'size' => 'lg',
                ])
                {{ ucfirst(__('modifica')) }}
                @endcomponent
                </div>
            @endcomponent
    </div>
</div>
<div class="row">
    <div class="col-lg-12 d-flex">
        @component('layouts.components.box')
            <h4 class="text-uppercase m-2">{{ __('PERMESSI SUI SITI WEB') }}</h4>
            <div>
                <div class="box-section lightgrey-bg-a1 my-4 py-5">
                    <h5 class="section-header mb-4">{{ __('ruolo') }}</h5>
                    <div class="row">
                        <div class="col-md-4 d-flex align-items-center">
                            <span class="badge user-role {{ $type }}">{{$type === "admin" ? __("Amministratore") : __("Analytics")}}</span>
                        </div>
                        <div class="col-md-8">
                            <p class="mb-0">
                                <svg class="icon"><use xlink:href="{{ asset('svg/sprite.svg#it-info-circle') }}"></use></svg>
                                {{
                                $type === "admin"
                                    ? __('L\'amministratore può gestire i siti web abilitati e tutti gli utenti')
                                    : __('Credenziale Analytics')
                                }}
                            </p>
                        </div>
                    </div>
                </div>
                @if ($type !== "admin")  
                    @include('partials.datatable')
                @endif
            </div>
            <div class="mt-4 text-center text-sm-left">
                @component('layouts.components.link_button', [
                    'icon' => 'it-pencil',
                    'link' => $credentialEditUrl,
                    'size' => 'lg',
                ])
                {{ ucfirst(__('modifica')) }}
                @endcomponent
                </div>
         @endcomponent
    </div>
</div>
@endsection
