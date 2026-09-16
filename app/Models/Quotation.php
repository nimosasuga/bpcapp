<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Carbon\Carbon;

class Quotation extends Model
{
    protected $fillable = [
        'quotation_number',
        'customer_id',
        'contact_id',
        'category',
        'quotation_date',
        'valid_until',
        'total_amount',
        'currency',
        'payment_terms',
        'lead_time',
        'pdf_file_path',
        'pdf_drive_path',
        'calendar_event_id',
        'status',
        'loss_reason',
        'loss_note',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'valid_until' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact()
    {
        return $this->belongsTo(CustomerContact::class, 'contact_id');
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function purchaseOrder()
    {
        return $this->hasOne(PurchaseOrder::class);
    }

    public function getDaysRemainingAttribute(): int
    {
        if (!$this->valid_until) {
            return 0;
        }
        return (int) Carbon::now()->startOfDay()->diffInDays($this->valid_until->startOfDay(), false);
    }

    public function getValidityBadgeColorAttribute(): string
    {
        if (in_array($this->status, ['WIN', 'CANCELLED'])) {
            return 'success';
        }
        if ($this->status === 'LOSE') {
            return 'gray';
        }

        $days = $this->days_remaining;
        if ($days < 0 || $this->status === 'EXPIRED') {
            return 'danger'; // Merah: Telah kadaluwarsa
        }
        if ($days <= 3) {
            return 'warning'; // Kuning: Masa berlaku tersisa <= 3 hari
        }
        return 'success'; // Hijau: Masa berlaku > 7 hari (atau aman)
    }

    public function getValidityStatusTextAttribute(): string
    {
        $days = $this->days_remaining;
        if ($days < 0) {
            return 'Expired (' . abs($days) . ' hari lalu)';
        }
        if ($days === 0) {
            return 'Hari Ini Berakhir!';
        }
        return $days . ' hari tersisa';
    }
}
