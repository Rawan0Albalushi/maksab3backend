{{-- Customer active/blocked switch. Flipped over ajax by
     `public/assets/admin/js/status-toggle.js` — `data-status-toggle` marks it,
     `data-method` makes it POST the new value rather than carry it in the URL.
     Styles: `admin-tables.css` (5c). --}}

<div class="status-toggle" data-status="{{ $customer->status ? 1 : 0 }}">
    <label class="toggle-switch toggle-switch-sm" for="customerStatus{{ $customer->id }}">
        <input type="checkbox" id="customerStatus{{ $customer->id }}"
               class="toggle-switch-input" data-status-toggle
               data-method="post"
               data-url="{{ route('admin.users.customer.status', $customer->id) }}"
               data-confirm-on="{{ translate('messages.You want to unblock this customer') }}"
               data-confirm-off="{{ translate('messages.You want to block this customer') }}"
               aria-label="{{ translate('Customer status') }}"
               {{ $customer->status ? 'checked' : '' }}>
        <span class="toggle-switch-label">
            <span class="toggle-switch-indicator"></span>
        </span>
    </label>
    <span class="status-toggle__text" aria-live="polite">
        {{ $customer->status ? translate('messages.Active') : translate('messages.Inactive') }}
    </span>
</div>
