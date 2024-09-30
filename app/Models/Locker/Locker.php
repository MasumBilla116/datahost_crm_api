<?php

namespace App\Models\Locker;

use App\Models\Users\ClientUsers;

use App\Models\Locker\LockerEntries;
use Illuminate\Database\Eloquent\Model;

class Locker extends Model
{

    protected $table = 'lockers';

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
    
    public function lockerEntry()
    {
        return $this->belongsTo(LockerEntries::class);
    }

    public function LockerEntriess()
    {
        return $this->belongsToMany(LockerEntries::class,'locker_entries_info');
    }
    
}