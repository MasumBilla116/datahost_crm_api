<?php

namespace App\Models\HRM;

use App\Models\Customers\Customer;
use App\Models\HRM\State;
use App\Models\Users\ClientUsers;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{

    protected $table = 'countries';

    protected $guarded = ['id'];


    public function states()
    {
        return $this->hasMany(State::class);
    }

    public function users(){
        return $this->hasMany(ClientUsers::class);
    }

    public function customers(){
        return $this->hasMany(Customer::class);
    }

}