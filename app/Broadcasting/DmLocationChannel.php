<?php

namespace App\Broadcasting;

use App\Models\User;

class DmLocationChannel
{
    public function __construct()
    {
    }

    public function join(User $user): array|bool
    {
        return true;
    }
}
