<?php

namespace App\Models;

use App\Scopes\HostScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::addGlobalScope(new HostScope());
    }
}
