<?php

namespace App\Models;

use App\Scopes\HostScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailVerifications extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new HostScope());
    }
}
