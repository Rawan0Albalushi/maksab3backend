<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use App\Traits\Model\HasStorageTrait;

class Refund extends Model
{
    use HasFactory, HasStorageTrait;
    protected $guarded = ['id'];


    protected $casts = [
        'refund_amount' => 'float',
        'order_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',

    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getImageFullUrlAttribute(){
        $images = [];
        $value = is_array($this->image)
            ? $this->image
            : ($this->image && is_string($this->image) && $this->isValidJson($this->image)
                ? json_decode($this->image, true)
                : []);
        if ($value){
            foreach ($value as $item){
                $item = is_array($item)?$item:(is_object($item) && get_class($item) == 'stdClass' ? json_decode(json_encode($item), true):['img' => $item, 'storage' => 'public']);
                $images[] = Helpers::get_full_url('refund',$item['img'],$item['storage']);
            }
        }

        return $images;
    }

    private function isValidJson($string)
    {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    protected static function boot()
    {
        parent::boot();

    }
}
