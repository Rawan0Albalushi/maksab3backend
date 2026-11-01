<?php

namespace App\Models;

use App\Traits\Model\HasEmailTemplateAttributes;
use App\Traits\Model\HasStorageTrait;
use App\Traits\Model\HasTranslationsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasEmailTemplateAttributes, HasFactory, HasStorageTrait, HasTranslationsTrait;

    protected $with = ['storage'];
}
