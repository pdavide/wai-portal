@extends('layouts.page', ['graphicBackground' => true])

@section('title', "Aggiungi credenziale")

@section('content')
@include('pages.credentials.partials.form', ['route' => $credentialsStoreUrl])
@endsection
