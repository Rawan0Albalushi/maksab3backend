@if (filled($address ?? null))
    <div class="pos-address">
        <div class="pos-address-row">
            <i class="tio-user"></i>
            <span>
                {{ $address['contact_person_name'] }}
                <span class="pos-address-label">·</span>
                {{ $address['contact_person_number'] }}
            </span>
        </div>
        @if (filled($address['address'] ?? null))
            <div class="pos-address-row">
                <i class="tio-poi"></i>
                <span>{{ $address['address'] }}</span>
            </div>
        @endif
        @if (count($address_extra ?? []))
            <div class="pos-address-row">
                <i class="tio-home-outlined"></i>
                <span>
                    @foreach ($address_extra as $label => $value)
                        <span class="pos-address-label">{{ $label }}:</span>{{ $value }}{{ !$loop->last ? ',' : '' }}
                    @endforeach
                </span>
            </div>
        @endif
    </div>
@else
    <div class="pos-empty pos-empty--compact">
        <span class="pos-empty-icon"><i class="tio-poi-outlined"></i></span>
        <p class="pos-empty-text">
            {{ translate('messages.No delivery address yet. Add one to calculate the delivery fee.') }}
        </p>
    </div>
@endif
