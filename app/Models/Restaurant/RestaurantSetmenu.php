<?php

namespace App\Models\Restaurant;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class RestaurantSetmenu extends Model
{

    protected $table = 'restaurant_setmenus';

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

    public function RestaurantFoods()
    {
        return $this->belongsToMany(RestaurantFood::class, "food_setmenu");
    }
}
