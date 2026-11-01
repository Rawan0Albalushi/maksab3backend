<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Model\HasTranslationsTrait;

class EmployeeRole extends Model
{
    use HasFactory, HasTranslationsTrait;

    public function getNameAttribute($value)
    {
        return $this->translatedAttribute('name', $value);
    }

    public function employees()
    {
        return $this->hasMany(VendorEmployee::class, 'employee_role_id');
    }

}
