<?php

namespace App\Traits\Auth;

use Illuminate\Database\Eloquent\Model;

trait SetsHashedPasswordTrait
{
    public function setPassword(Model $model, string $password): bool
    {
        $model->timestamps = false;

        return $model->forceFill(['password' => bcrypt($password)])->save();
    }
}
