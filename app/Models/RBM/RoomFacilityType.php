<?php

namespace App\Models\RBM;

use App\Models\RBM\RoomFacility;
use Illuminate\Database\Eloquent\Model;

class RoomFacilityType extends Model
{

    protected $table = 'room_facility_type';

    protected $guarded = [
        'id',
    ];

    public function roomType()
    {
        return $this->belongsToMany(RoomFacility::class,'room_facility_type');
    }


}
