<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_delivery_logs')) {
            return;
        }

        Schema::create('notification_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key', 64)->unique();
            $table->string('notification');
            $table->string('channel', 32);
            $table->string('recipient_type', 32)->nullable();
            $table->string('recipient_id', 64)->nullable();
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['notification', 'status']);
            $table->index(['recipient_type', 'recipient_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_logs');
    }
};
