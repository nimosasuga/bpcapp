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
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number', 100)->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('customer_contacts')->nullOnDelete();
            $table->enum('category', [
                'SPAREPART_JUNGHEINRICH',
                'TYRE_FORKLIFT',
                'TYRE_TRUCK_TIRON',
                'MIXED',
            ])->default('SPAREPART_JUNGHEINRICH');
            $table->date('quotation_date');
            $table->date('valid_until');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('IDR');
            $table->string('payment_terms', 100)->nullable();
            $table->string('lead_time', 100)->nullable();
            $table->text('pdf_file_path')->nullable();
            $table->text('pdf_drive_path')->nullable();
            $table->string('calendar_event_id', 255)->nullable();
            $table->enum('status', [
                'DRAFT',
                'SENT',
                'FOLLOW_UP',
                'WIN',
                'LOSE',
                'EXPIRED',
                'CANCELLED',
            ])->default('DRAFT');
            $table->enum('loss_reason', [
                'PRICE_TOO_HIGH',
                'COMPETITOR',
                'STOCK_UNAVAILABLE',
                'BUDGET_CANCELLED',
                'OTHER',
            ])->nullable();
            $table->text('loss_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
