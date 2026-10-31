<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 30); // salla | zid | shopify | woocommerce | amazon
            $table->string('name');
            $table->text('credentials')->nullable(); // encrypted:array cast, never returned by the API
            $table->string('external_store_id')->nullable();
            $table->string('status', 20)->default('pending'); // pending | connected | needs_reauth | error
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
