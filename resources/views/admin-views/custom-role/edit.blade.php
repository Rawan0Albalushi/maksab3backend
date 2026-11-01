@extends('layouts.admin.app')
@section('title',translate('Edit role'))

@push('css_or_js')
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/view-pages/custom-role.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/view-pages/custom-role.css')) }}">
@endpush

@section('content')
<div class="content container-fluid">

    @include('partials._page-head', [
        'title' => translate('messages.Employee role'),
        'subtitle' => translate('Change what this role can reach in the panel. Employees holding it are updated at once.'),
        'icon' => asset('public/assets/admin/img/edit.png'),
        'count' => null,
    ])

    <form action="{{route('admin.users.custom-role.update',[$role['id']])}}" method="post" class="role-form">
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
                        @foreach ($language as $lang)
                            <li class="nav-item">
                                <a class="nav-link lang_link" href="#"
                                    id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="lang_form" id="default-form">
                        <div class="form-group mb-0">
                            <label class="input-label" for="default_title">{{translate('Role name')}} ({{translate('Default')}})
                                <span class="form-label-secondary text-danger" data-toggle="tooltip" data-placement="right"
                                    data-original-title="{{ translate('messages.Required.')}}"> *</span>
                            </label>
                            <input type="text" name="name[]" id="default_title" class="form-control"
                                placeholder="{{translate('Role name example')}}" value="{{$role?->getRawOriginal('name')}}">
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                    </div>
                    @foreach($language as $lang)
                        @php($translate = collect($role['translations'])->firstWhere(fn ($t) => $t->locale == $lang && $t->key == 'name'))
                        <div class="d-none lang_form" id="{{$lang}}-form">
                            <div class="form-group mb-0 mt-3">
                                <label class="input-label" for="{{$lang}}_title">{{translate('Role name')}} ({{strtoupper($lang)}})</label>
                                <input type="text" name="name[]" id="{{$lang}}_title" class="form-control"
                                    placeholder="{{translate('Role name example')}}" value="{{$translate?->value}}">
                            </div>
                            <input type="hidden" name="lang[]" value="{{$lang}}">
                        </div>
                    @endforeach
                @else
                    <div id="default-form">
                        <div class="form-group mb-0">
                            <label class="input-label" for="default_title">{{translate('Role name')}} ({{ translate('Default') }})</label>
                            <input type="text" name="name[]" id="default_title" class="form-control"
                                placeholder="{{translate('Role name example')}}" value="{{$role['name']}}" maxlength="100">
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                    </div>
                @endif
            </div>
        </div>

        @include('admin-views.custom-role.partials._permission_form', [
            'permissionGroups' => $permissionGroups,
            'selectedModules' => (array) json_decode($role['modules']),
        ])

        <div class="rp-actions">
            <p class="rp-actions__note">{{ translate('An employee with no permission can still sign in, but every section stays locked.') }}</p>
            <div class="rp-actions__buttons">
                <a href="{{route('admin.users.custom-role.list')}}" class="btn btn--reset"><i class="tio-back-ui"></i> {{translate('messages.Back')}}</a>
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
