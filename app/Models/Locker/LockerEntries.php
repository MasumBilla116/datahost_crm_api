<?php

namespace App\Models\Locker;

use App\Models\Locker\Locker;

use App\Models\Users\ClientUsers;
use App\Models\Customers\Customer;
use Illuminate\Database\Eloquent\Model;
use App\Models\Locker\LockerEntriesInfo;
use App\Models\Locker\LockerLuggageInfo;
use App\Models\Locker\LockerLuggageItems;

class LockerEntries extends Model
{

    protected $table = 'locker_entries';

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
    
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'guest_id');
    }

    public function luggageInfo()
    {
        return $this->belongsToMany(LockerLuggageInfo::class, 'locker_luggage_info');
    }
    
    public function lockers()
    {
        return $this->hasMany(Locker::class);
    }
    
    public function luggageItems()
    {
        return $this->hasMany(LockerLuggageItems::class);
    }
    
    public function Lockerss()
    {
        return $this->belongsToMany(Locker::class,'locker_entries_info');
    }

    public function LockerLuggageItemsss()
    {
        return $this->belongsToMany(LockerLuggageItems::class,'locker_luggage_info');
    }

}