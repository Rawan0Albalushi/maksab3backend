@extends('layouts.landing.app')

@section('title',translate('Refund policy'))

@section('content')
    <section class="page-hero">
        <div class="container">
            <h1>{{ translate('Refund policy') }}</h1>
            <div class="breadcrumb">
                <a href="{{route('home')}}">{{ translate('messages.home') }}</a> / {{ translate('Refund policy') }}
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
