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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->string('po_number', 100);
            $table->date('po_date');
            $table->decimal('po_amount', 15, 2)->default(0);
            $table->text('po_file_path')->nullable();
            $table->text('po_file_drive_path')->nullable();
            $table->enum('delivery_status', [
                'PENDING',
                'PARTIAL',
                'DELIVERED',
                'COMPLETED',
            ])->default('PENDING');
            $table->date('delivery_due_date')->nullable();
            $table->string('surat_jalan_ref', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
