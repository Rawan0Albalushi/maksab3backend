<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('storages')->whereRaw('data_id NOT REGEXP "^[0-9]+$"')->exists()) {
            Schema::table('storages', function (Blueprint $table) {
                $table->unsignedBigInteger('data_id')->nullable(false)->change();
            });
        }

        Schema::table('storages', function (Blueprint $table) {
            $table->index(['data_type', 'data_id'], 'storages_data_type_data_id_index');
        });

        Schema::table('translations', function (Blueprint $table) {
            $table->index(
                ['translationable_type', 'translationable_id', 'locale'],
                'translations_type_id_locale_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('storages', function (Blueprint $table) {
            $table->dropIndex('storages_data_type_data_id_index');
        });

        Schema::table('translations', function (Blueprint $table) {
            $table->dropIndex('translations_type_id_locale_index');
        });

        Schema::table('storages', function (Blueprint $table) {
            $table->string('data_id', 100)->nullable(false)->change();
        });
    }
};
