{{-- The "who the money moved to or from" cell of a Cash Operations table.

     @include('admin-views.cash.partials._payee-cell', [
         'payee_url'    => route('admin.users.delivery-man.preview', [$dm->id]),
         'payee_avatar' => $dm->image_full_url,
         'payee_name'   => trim($dm->f_name . ' ' . $dm->l_name),
         'payee_sub'    => $dm->phone,
         'payee_store'  => false,     // square crop; a person gets the circle
         'payee_gone'   => translate('messages.Deliveryman deleted'),
     ])

     Pass `payee_url => null` when the record is gone. The ledger row stays —
     the money still moved — so the cell renders `payee_gone` rather than a
     dead link.

     Styles: `cash.css` §3. --}}

@php
    $csh_fallback = asset('public/assets/admin/img/160x160/img1.jpg');
    $csh_name = trim($payee_name ?? '');
    $csh_store = $payee_store ?? false;
@endphp

@if($payee_url && $csh_name !== '')
    <a href="{{ $payee_url }}" class="csh-payee {{ $csh_store ? 'csh-payee--store' : '' }}" title="{{ $csh_name }}">
        <img class="csh-payee__avatar onerror-image" data-onerror-image="{{ $csh_fallback }}"
             src="{{ $payee_avatar ?: $csh_fallback }}" alt="">
        <span class="csh-payee__text">
            <span class="csh-payee__name">{{ Str::limit($csh_name, 24, '...') }}</span>
            <span class="csh-payee__sub">{{ $payee_sub }}</span>
        </span>
    </a>
@else
    <span class="csh-payee--gone"><i class="tio-user-outlined"></i> {{ $payee_gone }}</span>
@endif
