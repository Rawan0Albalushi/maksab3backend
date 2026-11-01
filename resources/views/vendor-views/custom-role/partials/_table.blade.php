@foreach($rl as $role)
    @php($permissions = array_values(array_intersect(array_keys($permissionLabels), (array) json_decode($role['modules']))))
    @php($employees = $role->employees_count ?? 0)
    @php($edited = $role->updated_at && $role->created_at && $role->updated_at->gt($role->created_at->copy()->addMinute()))
    <tr>
        <td>
            <a class="role-name d-block" href="{{route('vendor.custom-role.edit',[$role['id']])}}" title="{{ $role['name'] }}">{{ Str::limit($role['name'], 30, '...') }}</a>
            <span class="d-block fs-12 text-muted">ID:{{ $role['id'] }}</span>
        </td>
        <td>
            <div class="role-perms">
                <span class="role-perms__count">{{ count($permissions) }}<span class="role-perms__total">/{{ $permissionTotal }}</span></span>
                <span class="role-perms__chips">
                    @forelse (array_slice($permissions, 0, 3) as $key)
                        <span class="role-chip">{{ $permissionLabels[$key] }}</span>
                    @empty
                        <span class="role-perms__none">{{ translate('No permission') }}</span>
                    @endforelse
                    @if (count($permissions) > 3)
                        <span class="role-chip role-chip--more">+{{ count($permissions) - 3 }}</span>
                    @endif
                </span>
            </div>
        </td>
        <td class="col--numeric">
            @if ($employees)
                <span class="text--title font-weight-bold">{{ $employees }}</span>
            @else
                <span class="fs-12 text-muted">{{ translate('messages.No one yet') }}</span>
            @endif
        </td>
        <td>
            @if ($role->created_at)
                <span class="table-when">
                    <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($role->created_at) }}</span>
                    <span class="table-when__ago" title="{{ \App\CentralLogics\Helpers::time_date_format($edited ? $role->updated_at : $role->created_at) }}">{{ $edited ? translate('Edited') . ' ' . $role->updated_at->diffForHumans() : $role->created_at->diffForHumans() }}</span>
                </span>
            @else
                <span class="text-muted">&mdash;</span>
            @endif
        </td>
        <td>
            <div class="btn--container justify-content-center">
                <a class="btn action-btn action-btn--view offcanvas-trigger data-info-show"
                    data-id="{{$role['id']}}" data-url="{{route('vendor.custom-role.view',[$role['id']])}}"
                    href="#0" data-target="#offcanvas__role_table" title="{{translate('messages.View')}}">
                    <i class="tio-visible-outlined"></i>
                </a>
                <a class="btn action-btn action-btn--edit"
                    href="{{route('vendor.custom-role.edit',[$role['id']])}}" title="{{translate('Edit role')}}">
                    <i class="tio-edit"></i>
                </a>
                @if ($employees)
                    <span class="btn action-btn action-btn--delete disabled" aria-disabled="true"
                        title="{{translate('Move its employees to another role before deleting this one.')}}">
                        <i class="tio-delete-outlined"></i>
                    </span>
                @else
                    <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
                        data-id="role-{{$role['id']}}" data-message="{{translate('messages.This role will be removed for good. No employee holds it, so nobody loses access.')}}"
                        title="{{translate('messages.Delete role')}}">
                        <i class="tio-delete-outlined"></i>
                    </a>
                @endif
            </div>
            <form action="{{route('vendor.custom-role.delete',[$role['id']])}}" method="post" id="role-{{$role['id']}}">
                @csrf @method('delete')
            </form>
        </td>
    </tr>
@endforeach
