@extends('layouts.landing.app')

@section('title',translate('messages.Terms and condition'))

@section('content')
    <section class="page-hero">
        <div class="container">
            <h1>{{ translate('messages.Terms and condition') }}</h1>
            <div class="breadcrumb">
                <a href="{{route('home')}}">{{ translate('messages.home') }}</a> / {{ translate('messages.Terms and condition') }}
            </div>
        </div>
    </section>

    <section class="page-content">
        <div class="container">
            <div class="content-card">
                {!! $data !!}
            </div>
        </div>
    </section>
@endsection
