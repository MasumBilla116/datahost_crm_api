<?php

namespace App\Models\Restaurant;

use Carbon\Carbon;
use App\Models\Settings\TaxHead;

use App\Models\Users\ClientUsers;
use Illuminate\Database\Eloquent\Model;

class RestaurantFood extends Model
{

    protected $table = 'restaurant_foods';

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

    public function RestaurantSetmenus()
    {
        return $this->belongsToMany(RestaurantSetmenu::class);
    }

    public function TaxHead()
    {
        return $this->belongsTo(TaxHead::class);
    }

    public function RestaurantPromoOffer()
    {
        $now = Carbon::now();
        $to_day=date("Y-m-d",strtotime($now));
        return $this->belongsTo(RestaurantPromoOffer::class)->where('start_date','<=',$to_day)->where('ending_date','>=',$to_day);
    }
}
