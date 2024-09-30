<?php

namespace App\Models\Customers;

use Illuminate\Database\Eloquent\Model;
use App\Models\Customers\CustomerBookingGrp;
use App\Models\RBM\TowerFloorRoom;

class CustomerBooking extends Model
{

    protected $table = 'customer_booking_days';

    protected $guarded = [
        'id',
    ];

    public function bookingMaster(){
        return $this->belongsTo(CustomerBookingGrp::class);
    }

    public function room(){
        return $this->belongsTo(TowerFloorRoom::class,'room_id');
    }



}
