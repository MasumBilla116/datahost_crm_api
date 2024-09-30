<?php

namespace App\Models\HouseKeeping;

use App\Models\Users\ClientUsers;
use App\Models\RBM\TowerFloorRoom;
use App\Models\Housekeeping\HousekeepingCheck;

use Illuminate\Database\Eloquent\Model;

class HousekeeperTask extends Model
{

    protected $table = 'housekeeper_task';

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

    public function user()
    {
        return $this->belongsTo(ClientUsers::class);
    }

    public function towerfloorroom()
    {
        return $this->hasMany(TowerFloorRoom::class);
    
    }
    public function task()
    {
        return $this->hasMany(HousekeepingCheck::class);
    }



    
}
