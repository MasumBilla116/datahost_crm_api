<?php

namespace App\Models\RBM;

use App\Models\Customers\CustomerBooking;
use App\Models\RBM\Tower;
use App\Models\RBM\RoomType;
use App\Models\RBM\TowerFloor;
use App\Models\RBM\RoomCategory;
use App\Models\Users\ClientUsers;
use Illuminate\Database\Eloquent\Model;

class TowerFloorRoom extends Model
{

    protected $table = 'tower_floor_rooms';

    protected $guarded = [
        'id',
    ];

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }
    public function tower()
    {
        return $this->belongsTo(Tower::class);
    }
    public function floor()
    {
        return $this->belongsTo(TowerFloor::class);
    }
    
    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }
    public function roomCategory()
    {
        return $this->belongsTo(RoomCategory::class);
    }

    public function bookingDays(){
        return $this->hasMany(CustomerBooking::class);
    }


}
