<?php

namespace App\Services\Admin;

use App\Support\Storage\FileStorage;

class EmployeeService
{

    public function getAddData(array $input): array
    {
        return [
            'f_name' => ($input['f_name'] ?? null),
            'l_name' => ($input['l_name'] ?? null),
            'phone' => ($input['phone'] ?? null),
            'zone_id' => ($input['zone_id'] ?? null),
            'email' => ($input['email'] ?? null),
            'role_id' => ($input['role_id'] ?? null),
            'password' => bcrypt(($input['password'] ?? null)),
            'image' => FileStorage::upload('admin/', ($input['image'] ?? null)),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    public function getUpdateData(array $input, object $employee): array
    {
        if (($input['password'] ?? null) == null) {
            $pass = $employee['password'];
        } else {
            $pass = bcrypt(($input['password'] ?? null));
            $employee->remember_token=null;
            $employee->login_remember_token=null;
        }

        if (array_key_exists('image', $input)) {
            $employee['image'] = FileStorage::update('admin/', $employee->image, ($input['image'] ?? null));
        }
        return [
            'f_name' => ($input['f_name'] ?? null),
            'l_name' => ($input['l_name'] ?? null),
            'phone' => ($input['phone'] ?? null),
            'zone_id' => ($input['zone_id'] ?? null),
            'email' => ($input['email'] ?? null),
            'role_id' => ($input['role_id'] ?? null),
            'password' => $pass,
            'image' => $employee['image'],
            'updated_at' => now(),
            'is_logged_in' => 0,
        ];
    }
    public function adminCheck(Object $employee): array
    {
        if (auth('admin')->id()  != $employee['id']){
            return ['flag' => 'unauthorized'];
        }
        return ['flag' => 'authorized'];
    }

}
