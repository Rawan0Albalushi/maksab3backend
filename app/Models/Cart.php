<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $casts = [
        'user_id' => 'integer',
        'module_id' => 'integer',
        'store_id' => 'integer',
        'item_id' => 'integer',
        'reel_id' => 'integer',
        'is_guest' => 'boolean',
        'price' => 'float',
        'quantity' => 'integer',
        'add_on_ids' => 'array',
        'add_on_qtys' => 'array',
        'variation' => 'array',
    ];

    protected $fillable = [
        'user_id',
        'module_id',
        'store_id',
        'item_id',
        'reel_id',
        'is_guest',
        'add_on_ids',
        'add_on_qtys',
        'item_type',
        'price',
        'quantity',
        'variation',
        // The BOGO columns are fillable so a bundle row survives mass assignment. The add path
        // writes through Cart::insert(), which bypasses this list entirely, so their absence went
        // unnoticed -- but anything routing a bundle row through Cart::create() or ->update()
        // silently dropped them, which turns a free member into an ordinary line at full price
        // and detaches a buy line from the group that priced it.
        'bogo_offer_id',
        'bogo_group_id',
        'is_free_item',
        'bundle_id',
        'bundle_group_id',
    ];

    public function item()
    {
        return $this->morphTo();
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
