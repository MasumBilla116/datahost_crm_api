<?php

namespace App\Models\RBM\RoomHourlySlot;

use App\Models\RBM\RoomType;
use Illuminate\Database\Eloquent\Model;
use App\Models\RBM\RoomHourlySlot\RoomPriceHourly;

class RoomHourSlot extends Model
{

    protected $table = 'room_hourly_slots';

    protected $guarded = [
        'id',
    ];

    public function roomTypes()
    {
        return $this->belongsToMany(RoomType::class,'room_types_hour_slots_info','hour_slot_id','room_type_id');
    }

    public function hourlyRoomPrices()
    {
        return $this->hasMany(RoomPriceHourly::class,'hour_slot_id');
    }


}
