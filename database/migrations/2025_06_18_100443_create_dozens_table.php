<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dozens', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->uuid("user_id");
            $table->uuid("product_id");
            $table->string('barcode')->unique();
            $table->string('color');
            $table->integer('total_pieces')->default(10);
            $table->decimal('purchase_price', 10, 2);  // price paid per dozen
            $table->decimal('selling_price', 10, 2);   // price you sell per piece

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dozens');
    }
};
