<?php

namespace App\Models\Customers;
use Illuminate\Database\Eloquent\Model;

class BookingNote extends Model
{

    protected $table = 'customer_booking_notes';

    protected $guarded = [
        'id',
    ];


}
