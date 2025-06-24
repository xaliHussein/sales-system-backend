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
        Schema::create('items', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->uuid("user_id");
            $table->uuid("product_id");
            $table->uuid("dozen_id");
            $table->string('size');                      // different sizes per piece
            $table->integer('quantity');
            $table->enum('status', ['available', 'sold_out'])->default('available');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
