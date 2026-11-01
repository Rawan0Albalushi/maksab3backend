{{--
    What is inside each BOGO bundle on this order, opened by clicking its row.

    Rendered after the table rather than inside it: a modal nested in a <tbody> is invalid markup
    and browsers relocate it, which detaches it from its trigger.

    Expects: $bogoGroups (from BogoOrderService::orderGroups).
--}}
@foreach ($bogoGroups['bundles'] as $bogo)
    @php
        $modalId = 'bogo_details_'.\Illuminate\Support\Str::slug($bogo['group_id']);
    @endphp
    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        {{ $bogo['offer_title'] ?: translate('BOGO offer') }}
                        <span class="badge badge-soft-info ml-1">{{ translate('messages.BOGO') }}</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between fs-12 mb-3">
                        <span class="text-muted">{{ translate('messages.QTY') }} :
                            <strong>{{ $bogo['quantity'] }}</strong></span>
                        <span class="text-muted">
                            {{ \App\CentralLogics\Helpers::format_currency($bogo['unit_price']) }}
                            {{ translate('messages.per bundle') }}
                        </span>
                        <span><strong>{{ \App\CentralLogics\Helpers::format_currency($bogo['total']) }}</strong></span>
                    </div>

                    @foreach ([
                        ['label' => translate('Buying item'), 'rows' => $bogo['buy'], 'free' => false],
                        ['label' => translate('Free item'), 'rows' => $bogo['free'], 'free' => true],
                    ] as $side)
                        @if (count($side['rows']))
                            <div class="mb-3">
                                <div class="fs-12 text-muted mb-2">{{ $side['label'] }}</div>
                                @foreach ($side['rows'] as $line)
                                    @php
                                        $lineName = \App\CentralLogics\Helpers::decodeJsonToArray($line->item_details)['name']
                                            ?? ($line->item?->name ?? translate('item'));
                                        $lineAddOns = \App\CentralLogics\Helpers::decodeJsonToArray($line->add_ons);
                                    @endphp
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <img width="36" height="36" class="rounded border onerror-image"
                                             src="{{ $line->item?->image_full_url ?? asset('public/assets/admin/img/100x100/2.png') }}"
                                             data-onerror-image="{{ asset('public/assets/admin/img/100x100/2.png') }}"
                                             alt="{{ $lineName }}">
                                        <div class="d-flex flex-column">
                                            <span class="fs-12 text-dark">{{ $lineName }}</span>
                                            <span class="fs-10 text-muted">
                                                {{ translate('messages.QTY') }} : {{ $line->quantity }}
                                                @if ($side['free'])
                                                    &middot; <span class="text-success">{{ translate('messages.Free') }}</span>
                                                @else
                                                    &middot; {{ \App\CentralLogics\Helpers::format_currency($line->price) }}
                                                @endif
                                            </span>
                                            {{-- Name and count, no price. Every BOGO line on record carries
                                                 `total_add_on_price` 0: what the member chose is already inside
                                                 the bundle's frozen price, so printing the add-on's menu price
                                                 here would read as a charge on top of it. Same reasoning as the
                                                 empty Addons cell in _bogo_order_row.blade.php, which defers to
                                                 this modal for what the bundle actually carries. --}}
                                            @if (count($lineAddOns))
                                                <span class="fs-10 text-muted">
                                                    {{ translate('Addons') }} :
                                                    {{ collect($lineAddOns)
                                                        ->map(fn ($addon) => ($addon['name'] ?? translate('Addon')).' x '.($addon['quantity'] ?? 1))
                                                        ->implode(', ') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endforeach
