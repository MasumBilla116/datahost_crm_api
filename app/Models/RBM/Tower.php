<?php

namespace App\Models\RBM;

use App\Models\RBM\TowerFloor;
use App\Models\Users\ClientUsers;
use App\Models\RBM\TowerFloorRoom;
use Illuminate\Database\Eloquent\Model;

class Tower extends Model
{

    protected $table = 'towers';

    protected $guarded = [
        'id',
    ];

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }
    public function floors()
    {
        return $this->hasMany(TowerFloor::class);
    }
    public function rooms()
    {
        return $this->hasMany(TowerFloorRoom::class);
    }

}
