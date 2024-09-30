<?php

namespace App\Models\Inventory;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class WarehouseLevel extends Model
{

    protected $table = 'warehouse_levels';

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

    public function locations()
    {
        return $this->hasMany(WarehouseLocation::class);
    }
}
