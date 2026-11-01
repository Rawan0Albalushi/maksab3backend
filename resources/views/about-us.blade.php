@extends('layouts.landing.app')

@section('title',translate('About us'))

@section('content')
    <section class="page-hero">
        <div class="container">
            <h1>{{ translate('About us') }}</h1>
            <div class="breadcrumb">
                <a href="{{route('home')}}">{{ translate('messages.home') }}</a> / {{ translate('About us') }}
            </div>
        </div>
    </section>

    <section class="page-content">
        <div class="container">
            <div class="content-card">
                <h2>{{ $data_title }}</h2>
                <div>{!! $data !!}</div>
            </div>
        </div>
    </section>
@endsection
