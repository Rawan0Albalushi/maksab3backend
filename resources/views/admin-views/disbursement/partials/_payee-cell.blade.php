{{-- The "who is being paid" cell of a disbursement details table.

     @include('admin-views.disbursement.partials._payee-cell', [
         'payee_url'    => route('admin.users.rider.preview', $detail->delivery_man_id),
         'payee_avatar' => $detail->rider?->image_full_url,
         'payee_name'   => trim($detail->rider?->f_name . ' ' . $detail->rider?->l_name),
         'payee_sub'    => $detail->rider?->phone,
         'payee_person' => true,   // round crop; a store keeps the squircle
     ])

     Pass `payee_url => null` for a row whose payee record has gone missing;
     the cell then renders the fallback line instead of a dead link.

     Styles: `disbursement.css` §6. --}}

@php
    $sdb_fallback = asset('public/assets/admin/img/160x160/img1.jpg');
    $sdb_name = trim($payee_name ?? '');
    $sdb_person = $payee_person ?? false;
@endphp

@if($payee_url && $sdb_name !== '')
    <a href="{{ $payee_url }}" class="sdb-store {{ $sdb_person ? 'sdb-store--person' : '' }}" title="{{ $sdb_name }}">
        <img class="sdb-store__avatar onerror-image" data-onerror-image="{{ $sdb_fallback }}"
             src="{{ $payee_avatar ?: $sdb_fallback }}" alt="">
        <span class="sdb-store__text">
            <span class="sdb-store__name">{{ Str::limit($sdb_name, 24, '...') }}</span>
            <span class="sdb-store__sub">{{ $payee_sub }}</span>
        </span>
    </a>
@else
    <span class="text-muted">{{ translate('No data found') }}</span>
@endif
