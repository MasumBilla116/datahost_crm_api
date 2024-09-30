<?php

namespace App\Models\RBM\RoomHourlySlot;

use App\Models\RBM\RoomType;
use Illuminate\Database\Eloquent\Model;
use App\Models\RBM\RoomHourlySlot\RoomHourSlot;

class RoomPriceHourly extends Model
{

    protected $table = 'room_prices_hourly';

    protected $guarded = [
        'id',
    ];

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }
    public function hourSlot()
    {
        return $this->belongsTo(RoomHourSlot::class);
    }


}
