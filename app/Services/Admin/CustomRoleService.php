<?php

namespace App\Services\Admin;


class CustomRoleService
{

    public function getAddData(array $input): array
    {
        return [
            'name' => ($input['name'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'modules' => json_encode(($input['modules'] ?? null)),
            'status' => 1,
        ];
    }

    public function roleCheck(string|int $role): array
    {
        if($role == 1)
        {
            return ['flag' => 'unauthorized'];
        }
        return ['flag' => 'authorized'];
    }

}
