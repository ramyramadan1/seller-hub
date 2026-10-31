<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_wallets', function (Blueprint $table) {
            $table->string('tenant_id', 36)->primary();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_wallets');
    }
};
