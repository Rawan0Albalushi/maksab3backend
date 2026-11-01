@extends('layouts.landing.app')

@section('title',translate('Privacy policy'))

@section('content')
    <section class="page-hero">
        <div class="container">
            <h1>{{ translate('Privacy policy') }}</h1>
            <div class="breadcrumb">
                <a href="{{route('home')}}">{{ translate('messages.home') }}</a> / {{ translate('Privacy policy') }}
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
