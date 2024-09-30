<?php

namespace App\Models\RoomServices;

use Illuminate\Database\Eloquent\Model;

class RoomService extends Model
{

    protected $table = 'cust_room_service_inv';

    protected $guarded = [
        'id',
    ];


}