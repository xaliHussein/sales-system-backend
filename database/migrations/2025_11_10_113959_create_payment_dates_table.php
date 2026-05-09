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
        Schema::create('payment_dates', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->uuid('debtor_user_id');
            $table->uuid("sale_id")->nullable();
            $table->decimal('amount_paid', 10, 2);
            $table->decimal('remaining_amount', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_dates');
    }
};
