<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Central lookup so webhooks / OAuth callbacks (no logged-in user) can find the right store.
        // channel_id points into the tenant database, so it is deliberately NOT a foreign key.
        Schema::create('channel_routes', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('channel_id');
            $table->string('driver', 30);
            $table->string('external_store_id')->nullable();
            $table->string('webhook_token', 64)->unique();
            $table->timestamps();

            $table->unique(['tenant_id', 'channel_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_routes');
    }
};
