@if ($store_data)
    <div class="pos-store-chip">
        <img class="pos-store-chip-logo onerror-image" src="{{ $store_data->logo_full_url }}"
             data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
             width="42" height="42" alt="{{ $store_data->name }}">
        <span class="pos-store-chip-text">
            <span class="pos-store-chip-name" title="{{ $store_data->name }}">
                {{ $store_data->name }}
                @if ($store_data->verified_seller)
                    <span class="pos-store-chip-badge">
                        <i class="tio-verified"></i>{{ translate('messages.verified') }}
                    </span>
                @endif
            </span>
            <span class="pos-store-chip-meta" title="{{ $store_data->address }}">{{ $store_data->address }}</span>
        </span>
    </div>
@endif
