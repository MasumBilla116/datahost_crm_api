<?php

namespace App\Models\Locker;


use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;
use App\Models\Locker\LockerLuggageItems;

class LockerLuggageInfo extends Model
{

    protected $table = 'locker_luggage_info';

    protected $guarded = [
        'id',
    ];

    public function LockerLuggageItemss()
    {
        return $this->belongsToMany(LockerLuggageItems::class,'locker_luggage_info');
    }
    
}