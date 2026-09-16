<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'company_name',
        'branch_area',
        'industry_type',
        'customer_type',
        'drive_folder_id',
        'address',
    ];

    public function contacts()
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function primaryContact()
    {
        return $this->hasOne(CustomerContact::class)->where('is_primary', true);
    }

    public function fleets()
    {
        return $this->hasMany(CustomerFleet::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function getWinRatioAttribute(): float
    {
        $total = $this->quotations()->whereIn('status', ['WIN', 'LOSE'])->count();
        if ($total === 0) {
            return 0.0;
        }
        $wins = $this->quotations()->where('status', 'WIN')->count();
        return round(($wins / $total) * 100, 1);
    }
}
