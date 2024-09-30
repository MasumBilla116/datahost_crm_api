<?php

namespace App\Models\RBM;

use App\Models\RBM\RoomType;
use Illuminate\Database\Eloquent\Model;

class RoomFacility extends Model
{

    protected $table = 'room_facilities';

    protected $guarded = [
        'id',
    ];

    public function roomTypes()
    {
        return $this->belongsToMany(RoomType::class,'room_facility_type');
    }


}
