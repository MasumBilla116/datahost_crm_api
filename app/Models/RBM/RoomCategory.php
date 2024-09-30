<?php

namespace App\Models\RBM;

use App\Models\RBM\TowerFloorRoom;
use Illuminate\Database\Eloquent\Model;

class RoomCategory extends Model
{

    protected $table = 'room_categories';

    protected $guarded = [
        'id',
    ];

    public function rooms()
    {
        return $this->hasMany(TowerFloorRoom::class);
    }


}
