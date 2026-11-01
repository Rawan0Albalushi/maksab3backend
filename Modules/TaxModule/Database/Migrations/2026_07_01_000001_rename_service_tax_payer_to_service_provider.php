<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['system_tax_setups', 'order_taxes'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tax_payer')) {
                DB::table($table)->where('tax_payer', 'service')->update(['tax_payer' => 'service_provider']);
            }
        }
    }

    public function down(): void
    {
        foreach (['system_tax_setups', 'order_taxes'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tax_payer')) {
                DB::table($table)->where('tax_payer', 'service_provider')->update(['tax_payer' => 'service']);
            }
        }
    }
};
