@foreach($employees as $employee)
    @php($is_self = auth('admin')->id() == $employee->id)
    <tr>
        <td>
            <span class="table-rest-info">
                <img class="img--60 rounded-circle onerror-image" data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                     src="{{ $employee->image_full_url }}" alt="{{ $employee->f_name }}">
                <span class="info max-w-200px">
                    <span class="d-block text--title text-capitalize line--limit-1">{{ $employee->f_name }} {{ $employee->l_name }}</span>
                    <span class="d-block font-light">ID:{{ $employee->id }}</span>
                    @if($is_self)
                        <span class="cell-chips mt-1"><span class="cell-chip">{{ translate('messages.You') }}</span></span>
                    @endif
                </span>
            </span>
        </td>
        <td>
            <a class="d-block text-body text-break" href="mailto:{{ $employee->email }}">{{ $employee->email }}</a>
            <a class="d-block fs-12 text-muted" href="tel:{{ $employee->phone }}">{{ $employee->phone }}</a>
        </td>
        <td>
            @if($employee->role)
                <span class="badge badge-soft-info">{{ $employee->role->name }}</span>
            @else
                <span class="badge badge-soft-danger">{{ translate('messages.Role deleted') }}</span>
            @endif
        </td>
        <td>{{ $employee->zones?->name ?? translate('messages.All zones') }}</td>
        <td>
            <span class="table-when">
                <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($employee->created_at) }}</span>
                <span class="table-when__ago" title="{{ \App\CentralLogics\Helpers::time_date_format($employee->created_at) }}">{{ \Carbon\Carbon::parse($employee->created_at)->diffForHumans() }}</span>
            </span>
        </td>
        <td class="text-center">
            @if($is_self)
                <span class="text-muted" title="{{ translate('messages.You cannot edit or remove your own account.') }}">&mdash;</span>
            @else
                <div class="btn--container justify-content-center">
                    <a class="btn action-btn action-btn--edit" href="{{ route('admin.users.employee.edit', [$employee->id]) }}" title="{{ translate('Edit employee') }}"><i class="tio-edit"></i></a>
                    <a class="btn action-btn action-btn--delete form-alert" href="javascript:" data-id="employee-{{ $employee->id }}"
                       data-message="{{ translate('messages.This employee will lose access to your panel straight away.') }}"
                       title="{{ translate('Delete employee') }}"><i class="tio-delete-outlined"></i></a>
                </div>
                <form action="{{ route('admin.users.employee.delete', [$employee->id]) }}" method="post" id="employee-{{ $employee->id }}">
                    @csrf @method('delete')
                </form>
            @endif
        </td>
    </tr>
@endforeach
