@extends('errors::minimal')

@section('title', translate('Too many requests'))
@section('code', '429')
@section('message', translate('Too many requests'))
