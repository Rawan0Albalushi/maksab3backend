@php
    $permissions = json_decode($role->modules, true) ?? [];
    $remaining = $permissions;
    $sections = [];

    foreach ($permissionGroups as $group) {
        $keys = [];
        foreach ($group['cards'] as $card) {
            $keys = array_merge($keys, array_values(array_intersect(array_keys($card['items']), $permissions)));
        }
        if ($keys === []) {
            continue;
        }
        $sections[] = ['label' => $group['label'], 'icon' => $group['icon'], 'keys' => $keys];
        $remaining = array_diff($remaining, $keys);
    }

    if ($remaining !== []) {
        $sections[] = ['label' => translate('Other'), 'icon' => null, 'keys' => $remaining];
    }
@endphp

<div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
    <h3 class="mb-0">{{ translate('Employee role') }}</h3>
    <button type="button"
        class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary text-dark offcanvas-close fz-15px p-0"
        aria-label="{{ translate('messages.Close') }}">&times;</button>
</div>

<div class="custom-offcanvas-body p-20 rp-view">
    <div class="rp-view__summary">
        <div class="rp-view__name">{{ $role->name }}</div>
        <div class="rp-view__meta">
            {{ translate('Permitted management') }}
            <span class="rp-view__badge">{{ count($permissions) }}</span>
        </div>
    </div>

    @forelse ($sections as $section)
        <div class="rp-view__group">
            <h6 class="rp-view__group-title">
                @if ($section['icon'])<i class="{{ $section['icon'] }}"></i>@endif
                {{ $section['label'] }}
            </h6>
            <div class="rp-view__pills">
                @foreach ($section['keys'] as $key)
                    <span class="rp-view__pill">{{ $permissionLabels[$key] ?? translate($key) }}</span>
                @endforeach
            </div>
        </div>
    @empty
        <p class="rp-view__empty">{{ translate('This role has no permission yet, so every section stays locked.') }}</p>
    @endforelse
</div>

<div class="offcanvas-footer p-3 d-flex align-items-center justify-content-center gap-3">
    <button type="button" class="btn w-100 offcanvas-close btn--reset"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.Cancel') }}</button>
    <a href="{{route('admin.users.custom-role.edit',[$role['id']])}}" class="btn w-100 btn--primary"><i class="tio-edit"></i> {{ translate('Edit details') }}</a>
</div>
