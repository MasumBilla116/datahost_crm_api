<?php

namespace App\Models\Customers;

use App\Models\Users\ClientUsers;
use Illuminate\Database\Eloquent\Model;
use App\Models\Customers\CustomerBooking;

class CustomerBookingGrp extends Model
{

    protected $table = 'customer_booking_master';

    protected $guarded = [
        'id',
    ];

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }
    public function updator()
    {
        return $this->belongsTo(ClientUsers::class, 'updated_by');
    }

    //Has many booking days
    public function bookingDays()
    {
        return $this->hasMany(CustomerBooking::class, 'booking_master_id');
    }
}
