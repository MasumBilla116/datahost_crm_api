<?php

namespace App\Models\Customers;

use App\Models\HRM\City;
use App\Models\HRM\Country;
use App\Models\HRM\State;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{

    protected $table = 'corporate_clients';

    protected $guarded = [
        'id',
    ];

    public function country(){
        return $this->belongsTo(Country::class);
    }
    public function state(){
        return $this->belongsTo(State::class);
    }
    public function city(){
        return $this->belongsTo(City::class);
    }


}