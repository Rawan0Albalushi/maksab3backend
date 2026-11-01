<tr class="bundle-order-row cursor-pointer" data-toggle="modal" data-target="#{{ $bundle['modal_id'] }}"
    title="{{ translate('messages.View bundle items') }}">
    <td class="odv-items__sl">{{ $sl }}</td>
    <td>
        <div class="d-flex flex-column gap-1">
            <strong class="line--limit-1">{{ $bundle['name'] }}</strong>

            <div class="text-muted fs-12">
                {{ \App\CentralLogics\Helpers::format_currency($bundle['unit_price']) }} {{ translate('messages.Each') }}
            </div>

            <div class="d-flex align-items-center mt-1">
                @foreach (array_slice($bundle['lines'], 0, 3) as $line)
                    <img width="32" height="32" class="rounded border onerror-image"
                        style="margin-right:-8px; background:#fff;"
                        src="{{ $line['image'] ?? asset('public/assets/admin/img/100x100/2.png') }}"
                        data-onerror-image="{{ asset('public/assets/admin/img/100x100/2.png') }}"
                        alt="{{ $line['name'] }}" title="{{ $line['name'] }}">
                @endforeach
                @if (count($bundle['lines']) > 3)
                    <span class="fs-12 text-body ml-3">+{{ count($bundle['lines']) - 3 }}</span>
                @endif
            </div>
        </div>
    </td>
    @if ($hasAddons)
        <td></td>
    @endif
    @if ($hasQty)
        <td class="text-center">{{ $bundle['quantity'] }}</td>
    @endif
    <td class="text-right">{{ \App\CentralLogics\Helpers::format_currency($bundle['total']) }}</td>
</tr>
