<?php

namespace App\Models\RBM;

use App\Models\RBM\Tower;
use App\Models\Users\ClientUsers;
use App\Models\RBM\TowerFloorRoom;
use Illuminate\Database\Eloquent\Model;

class TowerFloor extends Model
{

    protected $table = 'tower_floors';

    protected $guarded = [
        'id',
    ];

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }
    public function tower()
    {
        return $this->belongsTo(Tower::class);
    }
    public function rooms()
    {
        return $this->hasMany(TowerFloorRoom::class);
    }

}
