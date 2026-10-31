<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id', 36)->primary(); // UUID; the tenant database is tenant_{id}
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('currency', 3)->default('SAR');
            $table->string('locale', 5)->default('en');
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('status', 20)->default('provisioning'); // provisioning|active|failed|suspended
            $table->string('ai_source_default', 10)->default('own'); // own|platform
            $table->timestamps();
            $table->json('data')->nullable(); // stancl internal keys (tenancy_db_name, ...)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
