<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            if (! Schema::hasColumn('wishlists', 'service_id')) {
                $table->unsignedBigInteger('service_id')->nullable();
                $table->index('service_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            if (Schema::hasColumn('wishlists', 'service_id')) {
                $table->dropIndex(['service_id']);
                $table->dropColumn('service_id');
            }
        });
    }
};
