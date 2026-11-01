@php($rowOffset = method_exists($roles, 'firstItem') ? $roles->firstItem() : 1)

@foreach($roles as $k => $role)
    @php($permissions = (array) json_decode($role['modules']))
    @php($employees = $role->employees_count ?? 0)
    <tr>
        <td>{{ $k + $rowOffset }}</td>
        <td>
            <a class="role-name" href="{{route('admin.users.custom-role.edit',[$role['id']])}}">{{ Str::limit($role['name'], 30, '...') }}</a>
        </td>
        <td>
            <div class="role-perms">
                <span class="role-perms__count">{{ count($permissions) }}<span class="role-perms__total">/{{ $permissionTotal }}</span></span>
                <span class="role-perms__chips">
                    @forelse (array_slice($permissions, 0, 3) as $key)
                        <span class="role-chip">{{ $permissionLabels[$key] ?? translate($key) }}</span>
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
            <span class="badge {{ $employees ? 'badge-soft-dark' : 'badge-soft-secondary' }}">{{ $employees }}</span>
        </td>
        <td>{{ $role['created_at'] ? date('d M Y', strtotime($role['created_at'])) : '-' }}</td>
        <td>
            <div class="btn--container justify-content-center">
                <a class="btn action-btn action-btn--view offcanvas-trigger data-info-show"
                    data-id="{{$role['id']}}" data-url="{{route('admin.users.custom-role.view',[$role['id']])}}"
                    href="#0" data-target="#offcanvas__role_table" title="{{translate('messages.View')}}">
                    <i class="tio-visible-outlined"></i>
                </a>
                <a class="btn action-btn action-btn--edit"
                    href="{{route('admin.users.custom-role.edit',[$role['id']])}}" title="{{translate('Edit role')}}">
                    <i class="tio-edit"></i>
                </a>
                @if ($employees)
                    <span class="btn action-btn action-btn--delete disabled" aria-disabled="true"
                        title="{{translate('Move its employees to another role before deleting this one.')}}">
                        <i class="tio-delete-outlined"></i>
                    </span>
                @else
                    <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
                        data-id="role-{{$role['id']}}" data-message="{{translate('Want to delete this role?')}}"
                        title="{{translate('messages.Delete role')}}">
                        <i class="tio-delete-outlined"></i>
                    </a>
                @endif
            </div>
            <form action="{{route('admin.users.custom-role.delete',[$role['id']])}}" method="post" id="role-{{$role['id']}}">
                @csrf @method('delete')
            </form>
        </td>
    </tr>
@endforeach
