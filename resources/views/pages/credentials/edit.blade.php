@extends('layouts.page')

@section('title', $credential->client_name)

@section('title-after')
<small class="text-muted ml-3">{{ __('modifica') }}</small>
@endsection

@section('content')
@include('pages.credentials.partials.form', ['route' => $updateUrl])
@endsection
