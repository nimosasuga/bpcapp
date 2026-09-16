<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerFleet extends Model
{
    protected $fillable = [
        'customer_id',
        'fleet_type',
        'brand',
        'model_type',
        'serial_number',
        'tyre_size_front',
        'tyre_size_rear',
        'notes',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
