<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'quotation_id',
        'po_number',
        'po_date',
        'po_amount',
        'po_file_path',
        'po_file_drive_path',
        'delivery_status',
        'delivery_due_date',
        'surat_jalan_ref',
        'notes',
    ];

    protected $casts = [
        'po_date' => 'date',
        'delivery_due_date' => 'date',
        'po_amount' => 'decimal:2',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }
}
