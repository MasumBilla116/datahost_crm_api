<?php

namespace App\Models\RBM;

use App\Models\RBM\AdditionalRoomPrice;
use Illuminate\Database\Eloquent\Model;

class RoomOccupancy extends Model
{

    protected $table = 'room_occupancies';

    protected $guarded = [
        'id',
    ];

    public function additionPrices()
    {
        return $this->hasMany(AdditionalRoomPrice::class);
    }


}
