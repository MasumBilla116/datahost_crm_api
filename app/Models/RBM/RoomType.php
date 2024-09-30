<?php

namespace App\Models\RBM;

use App\Models\RBM\RoomPrice;
use App\Models\RBM\RoomFacility;
use App\Models\RBM\RoomHourlySlot\RoomHourSlot;
use App\Models\RBM\RoomHourlySlot\RoomPriceHourly;
use App\Models\RBM\RoomOccupancy;
use App\Models\RBM\TowerFloorRoom;
use App\Models\Restaurant\RestaurantPromoOffer;
use Illuminate\Database\Eloquent\Model;

class RoomType extends Model
{

    protected $table = 'room_types';

    protected $guarded = [
        'id',
    ];

    public function rooms()
    {
        return $this->hasMany(TowerFloorRoom::class,'room_type_id');
    }

    public function roomFacilities()
    {
        return $this->belongsToMany(RoomFacility::class,'room_facility_type');
    }

    public function roomTypes()
    {
        return $this->hasMany(RoomType::class, 'parent_id');
    }

    public function childrenRoomTypes()
    {
        return $this->hasMany(RoomType::class, 'parent_id')->with('roomTypes');
    }

    //Many Room Price under room type
    public function roomPrices(){
        return $this->hasMany(RoomPrice::class);
    }

    //has many room occupancies
    public function roomOccupancies(){
        return $this->hasMany(RoomOccupancy::class);
    }

    //has many slots with pivot
    public function hourlySlots(){
        return $this->belongsToMany(RoomHourSlot::class,'room_types_hour_slots_info','room_type_id','hour_slot_id');
    }

    // has many prices
    public function hourlyRoomPrices(){
        return $this->hasMany(RoomPriceHourly::class);
    }

    //has many offers
    public function promoOffers(){
        return $this->belongsToMany(RestaurantPromoOffer::class,'room_type_promo_offer','room_type_id','promo_offer_id');
    }


}
