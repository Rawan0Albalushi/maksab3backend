@if ($store)
    <div class="pos-store-chip">
        <img class="pos-store-chip-logo onerror-image" src="{{ $store->logo_full_url }}"
             data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
             width="42" height="42" alt="{{ $store->name }}">
        <span class="pos-store-chip-text">
            <span class="pos-store-chip-name" title="{{ $store->name }}">
                {{ $store->name }}
                @if ($store->verified_seller)
                    <span class="pos-store-chip-badge">
                        <i class="tio-verified"></i>{{ translate('messages.verified') }}
                    </span>
                @endif
            </span>
            <span class="pos-store-chip-meta" title="{{ $store->address }}">{{ $store->address }}</span>
        </span>
    </div>
@endif
