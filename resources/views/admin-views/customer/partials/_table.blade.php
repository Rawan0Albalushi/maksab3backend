@foreach ($customers as $key => $customer)
<tr class="">
    <td class="">
        {{ $key + 1 }}
    </td>
    <td class="table-column-pl-0">
        <a href="{{ route('admin.users.customer.view', [$customer['id']]) }}" class="text--hover">
            {{ $customer['f_name'] . ' ' . $customer['l_name'] }}
        </a>
    </td>
    <td>
        <div>
            {{ $customer['email'] }}
        </div>
        <div>
            {{ $customer['phone'] }}
        </div>
    </td>
    @if(($tab ?? 'main') === 'storefront')
    <td>
        <label class="badge badge-soft-info">
            {{ ($publishedStoreLookup ?? [])[$customer->sub_tenant_id] ?? '—' }}
        </label>
    </td>
    @endif
    <td>
        <label class="badge">
            {{ $customer->order_count }}
        </label>
    </td>
    <td>
        @include('admin-views.customer.partials._status-toggle', ['customer' => $customer])
    </td>
    <td>
        <a class="btn action-btn action-btn--view"
            href="{{ route('admin.users.customer.view', [$customer['id']]) }}"
            title="{{ translate('messages.View customer') }}"><i
                class="tio-visible-outlined"></i>
        </a>
    </td>
</tr>
@endforeach
