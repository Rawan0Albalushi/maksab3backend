<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'is_self_delivery')) {
            Schema::table('orders', function (Blueprint $table) {
                // Nullable, not defaulted to 0: null means "placed before this column existed",
                // and callers must fall back to the store's live self-delivery setting for those
                // rows rather than reading a false "not self-delivery" for every historical order.
                $table->boolean('is_self_delivery')->nullable()->default(null);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'is_self_delivery')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('is_self_delivery');
            });
        }
    }
};
