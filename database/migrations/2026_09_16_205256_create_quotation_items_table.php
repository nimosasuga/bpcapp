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
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->string('part_number', 100);
            $table->text('description')->nullable();
            $table->enum('category', [
                'JUNGHEINRICH_PART',
                'TYRE_COUNTERBALANCE',
                'TIRON_RADIAL',
                'TIRON_BIAS',
                'OTHER',
            ])->default('OTHER');
            $table->integer('qty')->default(1);
            $table->string('uom', 20)->default('Pcs');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
    }
};
