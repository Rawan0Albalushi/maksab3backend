@php
    $rowOrder  = $order ?? null;
    $rowInfo   = $rowOrder ? app(\App\Services\Order\OrderService::class)->adjustedFeeForOrder($rowOrder) : null;
    $rowLayout = $layout ?? 'dl';
@endphp

@if ($rowInfo && ($rowInfo['is_express'] || $rowInfo['is_slightly']))
    @php
        $rowLabel  = $rowInfo['is_express']
            ? translate('Express delivery')
            : translate('Slightly delay delivery');
        $rowSign   = $rowInfo['is_express'] ? '+' : '-';
        $rowAmount = $rowInfo['type_charge'];
    @endphp
    @if ($rowLayout === 'tr3')
        <tr>
            <td style="width: 40%"></td>
            <td class="p-1 px-3">{{ $rowLabel }}</td>
            <td class="text-right p-1 px-3">
                {{ $rowSign }} {{ \App\CentralLogics\Helpers::format_currency($rowAmount) }}
            </td>
        </tr>
    @elseif ($rowLayout === 'tr')
        <tr>
            <td>{{ $rowLabel }}</td>
            <td class="text-right">
                {{ $rowSign }} {{ \App\CentralLogics\Helpers::format_currency($rowAmount) }}
            </td>
        </tr>
    @else
        <dt class="col-6 {{ $dtClass ?? '' }}">{{ $rowLabel }} :</dt>
        <dd class="col-6 {{ $ddClass ?? 'text-right' }}">
            {{ $rowSign }} {{ \App\CentralLogics\Helpers::format_currency($rowAmount) }}
        </dd>
    @endif
@endif
