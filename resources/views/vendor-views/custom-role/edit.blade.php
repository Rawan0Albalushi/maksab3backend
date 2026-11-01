@extends('layouts.vendor.app')
@section('title',translate('Edit role'))

@push('css_or_js')
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/view-pages/custom-role.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/view-pages/custom-role.css')) }}">
@endpush

@section('content')
<div class="content container-fluid">

    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/edit.png')}}" class="w--26" alt="">
            </span>
            <span>{{translate('Edit role')}}</span>
        </h1>
        <p class="page-header-desc">{{ translate('Change what this role can reach. Employees holding it are updated at once.') }}</p>
    </div>

    <form action="{{route('vendor.custom-role.update-role',[$role['id']])}}" method="post" class="role-form">
        @csrf
        <div class="rp-card">
            <div class="rp-card__head">
                <h2 class="rp-card__title">{{ $role['name'] }}</h2>
                <p class="rp-card__subtitle">{{ translate('messages.Create a role and assign its module and usage permissions.') }}</p>
            </div>
            <div class="rp-card__body">
                @if($language)
                    <ul class="nav nav-tabs mb-4">
                        <li class="nav-item">
                            <a class="nav-link lang_link active" href="#" id="default-link">{{translate('Default')}}</a>
                        </li>
                        @foreach ($languages as $lang)
                            <li class="nav-item">
                                <a class="nav-link lang_link" href="#" id="{{ $lang }}-link">{{ $languageLabels[$lang] }}</a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="lang_form" id="default-form">
                        <div class="form-group mb-0">
                            <label class="input-label" for="default_title">{{translate('Role name')}} ({{translate('Default')}})
                                <span class="form-label-secondary text-danger" data-toggle="tooltip" data-placement="right"
                                    data-original-title="{{ translate('messages.Required.')}}"> *</span>
                            </label>
                            <input type="text" name="name[]" id="default_title" class="form-control" required
                                placeholder="{{translate('Role name example')}}" value="{{$role?->getRawOriginal('name')}}" maxlength="191">
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                    </div>
                    @foreach ($languages as $lang)
                        @php($translate = collect($role['translations'])->firstWhere(fn ($t) => $t->locale == $lang && $t->key == 'name'))
                        <div class="d-none lang_form" id="{{$lang}}-form">
                            <div class="form-group mb-0 mt-3">
                                <label class="input-label" for="{{$lang}}_title">{{translate('Role name')}} ({{strtoupper($lang)}})</label>
                                <input type="text" name="name[]" id="{{$lang}}_title" class="form-control"
                                    placeholder="{{translate('Role name example')}}" value="{{$translate?->value}}" maxlength="191">
                            </div>
                            <input type="hidden" name="lang[]" value="{{$lang}}">
                        </div>
                    @endforeach
                @else
                    <div id="default-form">
                        <div class="form-group mb-0">
                            <label class="input-label" for="default_title">{{translate('Role name')}} ({{ translate('Default') }})
                                <span class="form-label-secondary text-danger" data-toggle="tooltip" data-placement="right"
                                    data-original-title="{{ translate('messages.Required.')}}"> *</span>
                            </label>
                            <input type="text" name="name[]" id="default_title" class="form-control" required
                                placeholder="{{translate('Role name example')}}" value="{{$role['name']}}" maxlength="191">
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                    </div>
                @endif
            </div>
        </div>

        @include('vendor-views.custom-role.partials._permission_form', ['selectedModules' => $selectedModules])

        <div class="rp-actions">
            <p class="rp-actions__note">{{ translate('An employee with no permission can still sign in, but every section stays locked.') }}</p>
            <div class="rp-actions__buttons">
                <a href="{{route('vendor.custom-role.index')}}" class="btn btn--reset"><i class="tio-back-ui"></i> {{translate('messages.Back')}}</a>
                <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{translate('Update')}}</button>
            </div>
        </div>
    </form>

</div>
@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/js/view-pages/custom-role-index.js')}}"></script>
@endpush
