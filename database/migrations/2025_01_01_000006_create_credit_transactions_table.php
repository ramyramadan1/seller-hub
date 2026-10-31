<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('kind', 10); // topup|reserve|charge|refund|adjust|bonus
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('ai_job_ref')->nullable(); // id of the job in the TENANT database (no cross-db FK)
            $table->string('payment_ref')->nullable()->unique(); // idempotency key for payment webhooks
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
