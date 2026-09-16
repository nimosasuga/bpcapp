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
        Schema::create('customer_fleets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->enum('fleet_type', ['FORKLIFT_JUNGHEINRICH', 'FORKLIFT_OTHER', 'TRUCK']);
            $table->string('brand', 100);
            $table->string('model_type', 100);
            $table->string('serial_number', 100)->nullable();
            $table->string('tyre_size_front', 50)->nullable();
            $table->string('tyre_size_rear', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_fleets');
    }
};
