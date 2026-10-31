<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No tenant_id column: the database itself is the tenant boundary.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->nullable()->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->nullable(); // used for COGS; analytics flags products where this is null
            $table->integer('stock')->default(0);
            $table->json('images')->nullable();
            $table->string('source_image')->nullable();
            $table->string('ai_status', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
