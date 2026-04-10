<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained();
            $table->foreignUuid('wallet_id')->constrained();
            $table->string('reference')->unique();
 
            $table->enum('type', ['credit', 'debit']);
            $table->enum('status', ['pending', 'completed', 'successful', 'failed', 'canceled', 'reversed'])->default('pending');
 
            $table->decimal('amount', 20, 8);
            $table->string('currency')->default('NGN');
 
            // For audit purposes
            $table->decimal('balance_before', 20, 8)->nullable();
            $table->decimal('balance_after', 20, 8)->nullable();
 
            $table->string('description')->nullable();
            $table->json('metadata')->nullable(); // For extra data
            $table->string('channel')->nullable();  // api, webhook, import, etc.
 
            // For reversals: link to original transaction
            $table->foreignUuid('parent_transaction_id')->nullable()->constrained('transactions');
 
            $table->timestamp('processed_at')->nullable();
            $table->softDeletes();
 
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['user_id', 'type', 'created_at']);
            $table->index(['wallet_id', 'created_at']);
            $table->index(['reference']);
            $table->index(['status', 'created_at']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
