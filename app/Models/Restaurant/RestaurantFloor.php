<?php

namespace App\Models\Restaurant;

use Illuminate\Database\Eloquent\Model;

class RestaurantFloor extends Model
{

    protected $table = 'restaurant_floors';

    protected $guarded = [
        'id',
    ];

    public function RestaurantTables()
    {
        return $this->hasMany(RestaurantTable::class)->where('status',1);
    }

}
