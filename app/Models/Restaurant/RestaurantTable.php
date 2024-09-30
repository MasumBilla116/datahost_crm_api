<?php

namespace App\Models\Restaurant;

use Illuminate\Database\Eloquent\Model;

class RestaurantTable extends Model
{

    protected $table = 'restaurant_tables';

    protected $guarded = [
        'id',
    ];

    public function RestaurantFloor()
    {
        return $this->belongsTo(RestaurantFloor::class);
    }

}
