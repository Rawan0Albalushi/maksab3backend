{{--
    One BOGO bundle as a single order line, shared by the admin and vendor order screens.

    No thumbnail and no badge on the line itself: an offer has no single image that means anything,
    and the two strips already say what it is. They are only a preview -- a bundle can hold more
    items than a table row has space for -- so the row opens a modal with the full contents.

    The price cell carries the total alone, like every other row: QTY has its own column, so
    repeating "x N" beside the money said the same thing twice.

    Expects: $bogo (one entry from BogoOrderService::orderGroups), $sl, $hasAddons, $hasQty.
    $hasQty is false on the vendor screen, whose table has no QTY column -- the cell count has to
    match the header or the row shifts every column after it.
--}}
@php
    $thumbLimit = 3;
    $modalId = 'bogo_details_'.\Illuminate\Support\Str::slug($bogo['group_id']);
@endphp
<tr class="bogo-order-row cursor-pointer" data-toggle="modal" data-target="#{{ $modalId }}"
    title="{{ translate('messages.View bundle items') }}">
    <td class="odv-items__sl">{{ $sl }}</td>
    <td>
        <div class="d-flex flex-column gap-1">
            <strong class="line--limit-1">{{ $bogo['offer_title'] ?: translate('BOGO offer') }}</strong>

            <div class="text-muted fs-12">
                {{ \App\CentralLogics\Helpers::format_currency($bogo['unit_price']) }} {{ translate('messages.Each') }}
            </div>

            <div class="d-flex flex-wrap align-items-start mt-1" style="gap: 1.25rem;">
                @foreach ([
                    ['label' => translate('Buying item'), 'rows' => $bogo['buy']],
                    ['label' => translate('Free item'), 'rows' => $bogo['free']],
                ] as $side)
                    @if (count($side['rows']))
                        <div>
                            <div class="fs-10 text-muted mb-1">{{ $side['label'] }}</div>
                            <div class="d-flex align-items-center">
                                @foreach ($side['rows']->take($thumbLimit) as $line)
                                    @php
                                        $lineName = \App\CentralLogics\Helpers::decodeJsonToArray($line->item_details)['name']
                                            ?? ($line->item?->name ?? translate('item'));
                                    @endphp
                                    <img width="32" height="32" class="rounded border onerror-image"
                                         style="margin-right:-8px; background:#fff;"
                                         src="{{ $line->item?->image_full_url ?? asset('public/assets/admin/img/100x100/2.png') }}"
                                         data-onerror-image="{{ asset('public/assets/admin/img/100x100/2.png') }}"
                                         alt="{{ $lineName }}" title="{{ $lineName }}">
                                @endforeach
                                @if (count($side['rows']) > $thumbLimit)
                                    <span class="fs-12 text-body ml-3">+{{ count($side['rows']) - $thumbLimit }}</span>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </td>
    @if ($hasAddons)
        {{-- Deliberately empty. A bundle's add-ons are inside its frozen price, so listing them
             here at their menu prices would read as a charge on top. The cell stays so the row
             keeps the table's column count; the modal shows what the bundle carries. --}}
        <td></td>
    @endif
    @if ($hasQty)
        <td class="text-center">{{ $bogo['quantity'] }}</td>
    @endif
    <td class="text-right">{{ \App\CentralLogics\Helpers::format_currency($bogo['total']) }}</td>
</tr>
