<?php

namespace App\Models\HRM;

use App\Models\HRM\City;
use App\Models\HRM\Country;
use App\Models\Users\ClientUsers;
use Illuminate\Database\Eloquent\Model;

class State extends Model
{

    protected $table = 'states';

    protected $guarded = ['id'];


    public function country()
    {
        return $this->belongsTo(Country::class);
    }
    public function cities()
    {
        return $this->hasMany(City::class);
    }

    public function users(){
        return $this->hasMany(ClientUsers::class);
    }

}