<?php

namespace App\Models\Restaurant;

use App\Models\RBM\RoomType;
use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class RestaurantPromoOffer extends Model
{

    protected $table = 'restaurant_promo_offers';

    protected $guarded = [
        'id',
    ];

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }

    public function updator()
    {
        return $this->belongsTo(ClientUsers::class, 'updated_by');
    }

    public function roomTypes(){
        return $this->belongsToMany(RoomType::class,'room_type_promo_offer','promo_offer_id','room_type_id');
    }
}
