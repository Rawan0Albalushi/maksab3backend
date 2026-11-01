<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Employee list') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Analytics') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Total employee')  }}- {{ $data['employees']->count() }}
                    <br>
                    {{ translate('Active employee')  }}- {{ $data['employees']->where('status',1)->count() }}
                    <br>
                    {{ translate('Inactive employee')  }}- {{ $data['employees']->where('status',0)->count() }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr>
                <th>{{ translate('Search criteria') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Search bar content')  }}- {{ $data['search'] ??translate('N/A') }}

                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
                </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{translate('Employee image')}}</th>
            <th>{{translate('First name')}}</th>
            <th>{{translate('Last name')}}</th>
            <th>{{translate('Phone')}}</th>
            <th>{{translate('email')}}</th>
            <th>{{translate('Role')}}</th>
            <th>{{translate('Joining date')}}</th>
        </thead>
        <tbody>
        @foreach($data['employees'] as $key => $employee)
        <tr>
            <td>{{$key+1}}</td>
            <td></td>
            <td>{{  $employee['f_name']  }}</td>
            <td>{{  $employee['l_name']  }}</td>
            <td>{{  $employee['phone']  }}</td>
            <td>{{  $employee['email']  }}</td>
            <td>{{  $employee->role?$employee->role['name']:translate('messages.Role deleted')  }}</td>
            <td>
                {{date('Y-m-d '.config('timeformat'),strtotime($employee->created_at))}}
            </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
