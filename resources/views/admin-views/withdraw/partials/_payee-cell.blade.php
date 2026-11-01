{{-- The "who is asking for the money" cell of a withdraw queue.

     @include('admin-views.withdraw.partials._payee-cell', [
         'payee_url'    => route('admin.users.rider.preview', [$wr->rider->id]),
         'payee_avatar' => $wr->rider->image_full_url,
         'payee_name'   => trim($wr->rider->f_name . ' ' . $wr->rider->l_name),
         'payee_sub'    => $wr->rider->phone,
         'payee_store'  => false,       // square crop; a person gets the circle
         'payee_gone'   => translate('messages.Rider deleted'),
     ])

     Pass `payee_url => null` when the payee record is gone. The request row
     stays — the money was still asked for — so the cell renders `payee_gone`
     rather than a dead link.

     Styles: `withdraw.css` §3. --}}

@php
    $wdr_fallback = asset('public/assets/admin/img/160x160/img1.jpg');
    $wdr_name = trim($payee_name ?? '');
    $wdr_store = $payee_store ?? false;
@endphp

@if($payee_url && $wdr_name !== '')
    <a href="{{ $payee_url }}" class="wdr-payee {{ $wdr_store ? 'wdr-payee--store' : '' }}" title="{{ $wdr_name }}">
        <img class="wdr-payee__avatar onerror-image" data-onerror-image="{{ $wdr_fallback }}"
             src="{{ $payee_avatar ?: $wdr_fallback }}" alt="">
        <span class="wdr-payee__text">
            <span class="wdr-payee__name">{{ Str::limit($wdr_name, 24, '...') }}</span>
            <span class="wdr-payee__sub">{{ $payee_sub }}</span>
        </span>
    </a>
@else
    <span class="wdr-payee--gone"><i class="tio-user-outlined"></i> {{ $payee_gone }}</span>
@endif
