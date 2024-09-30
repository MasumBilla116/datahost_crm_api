<?php

namespace App\Models\Locker;

use App\Models\Locker\Locker;

use App\Models\Users\ClientUsers;
use App\Models\Locker\LockerEntries;
use Illuminate\Database\Eloquent\Model;

class LockerEntriesInfo extends Model
{

    protected $table = 'locker_entries_info';

    protected $guarded = [
        'id',
    ];

    public function Lockers()
    {
        return $this->belongsToMany(Locker::class,'locker_entries_info');
    }
    
}