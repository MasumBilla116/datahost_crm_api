<?php

namespace App\Models\HRM;

use App\Models\HRM\State;
use App\Models\Users\ClientUsers;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{

    protected $table = 'cities';

    protected $guarded = ['id'];


    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function users(){
        return $this->hasMany(ClientUsers::class);
    }

}