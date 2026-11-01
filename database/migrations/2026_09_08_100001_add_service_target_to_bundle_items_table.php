<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundle_items', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable();
            $table->index('service_id');
        });

        DB::statement('UPDATE `bundle_items` SET `service_id` = NULL WHERE `service_id` IS NOT NULL');

        Schema::table('bundle_items', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->nullable()->change();
        });

        DB::statement(
            'ALTER TABLE `bundle_items`
             ADD CONSTRAINT `bundle_items_one_target`
             CHECK ((`item_id` IS NOT NULL) <> (`service_id` IS NOT NULL))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `bundle_items` DROP CONSTRAINT `bundle_items_one_target`');

        Schema::table('bundle_items', function (Blueprint $table) {
            $table->dropIndex(['service_id']);
            $table->dropColumn('service_id');
        });
    }
};
