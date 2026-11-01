<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = ['branch', 'holder_name', 'account_no'];

    public function up(): void
    {
        $present = array_values(array_filter(self::COLUMNS, fn ($column) => Schema::hasColumn('vendors', $column)));

        if ($present === []) {
            return;
        }

        Schema::table('vendors', function (Blueprint $table) use ($present) {
            $table->dropColumn($present);
        });
    }

    public function down(): void
    {
        $missing = array_values(array_filter(self::COLUMNS, fn ($column) => ! Schema::hasColumn('vendors', $column)));

        if ($missing === []) {
            return;
        }

        Schema::table('vendors', function (Blueprint $table) use ($missing) {
            foreach ($missing as $column) {
                $table->string($column)->nullable();
            }
        });
    }
};
