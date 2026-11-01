<?php

namespace App\Models;

use App\Traits\Model\DemoMaskableTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Traits\Model\HasStorageTrait;

/**
 * Class Admin
 *
 * @property int $id
 * @property string|null $f_name
 * @property string|null $l_name
 * @property string|null $phone
 * @property string $email
 * @property string|null $image
 * @property string|null $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $role_id
 * @property int|null $zone_id
 * @property bool $is_logged_in
 */

class Admin extends Authenticatable
{
    use Notifiable, DemoMaskableTrait, HasStorageTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'f_name',
        'l_name',
        'phone',
        'email',
        'image',
        'password',
        'remember_token',
        'login_remember_token',
        'role_id',
        'zone_id',
        'is_logged_in',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_logged_in' => 'boolean',
    ];
    protected $appends = ['image_full_url'];

    /**
     * Every admin request runs module_permission_check(), which reads role->modules, and the
     * sidebars read it again. Loading it with the admin costs the one query it already costs.
     */
    protected $with = ['role'];

    /**
     * @return BelongsTo
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class,'role_id');
    }

    /**
     * @return BelongsTo
     */
    public function zones(): BelongsTo
    {
        return $this->belongsTo(Zone::class,'zone_id');
    }
    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('admin', 'image', $this->image);
    }
    public function getFullNameAttribute(){
        return Str::limit($this->f_name.' '.$this->l_name, 15, '...') ;
    }
    public function getMaskedEmailAttribute(){

        if ($this->email) {
            [$name, $domain] = explode('@', $this->email);
            $maskedName = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 2));
            $domainParts = explode('.', $domain);
            $maskedDomain = str_repeat('*', strlen($domainParts[0])) .  end($domainParts);
            $maskedEmail = $maskedName . '@' . $maskedDomain;
        }
        return $maskedEmail ?? $this->email;
    }


    /**
     * @param $query
     * @return mixed
     */
    public function scopeZone($query): mixed
    {
        if(auth('admin')?->user() && auth('admin')->user()->zone_id)
        {
            return $query->where('zone_id', auth('admin')->user()->zone_id);
        }
        return $query;
    }
    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });
    }
}
