<?php

namespace App\Models\RBM;

use App\Models\RBM\RoomOccupancy;
use Illuminate\Database\Eloquent\Model;

class AdditionalRoomPrice extends Model
{

    protected $table = 'room_price_additional';

    protected $guarded = [
        'id',
    ];

    public function roomOccupancy()
    {
        return $this->belongsTo(RoomOccupancy::class);
    }


}
