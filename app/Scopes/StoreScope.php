<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\CentralLogics\Helpers;

class StoreScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * The vendor check lives here, at query time, rather than in the models' booted(). booted()
     * runs once, on the first instantiation -- and Item::observe() in EventServiceProvider does
     * that while providers boot, before StartSession, when no guard is logged in yet. The scope
     * was then never registered and every vendor could read and write every store's items.
     *
     * Limited to vendor-panel requests: every session guard shares one cookie, so a browser
     * logged into both the admin and the vendor panel would otherwise see the admin panel and
     * the storefront filtered down to that vendor's store.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        if (! request()->is('vendor-panel', 'vendor-panel/*')) {
            return;
        }

        if (auth('vendor')->check() || auth('vendor_employee')->check()) {
            $builder->where($model->qualifyColumn('store_id'), Helpers::get_store_id());
        }
    }
}
