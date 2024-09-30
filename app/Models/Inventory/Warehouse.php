<?php

namespace App\Models\Inventory;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{

    protected $table = 'warehouses';

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

    public function levels()
    {
        return $this->hasMany(WarehouseLevel::class);
    }

    public function locations()
    {
        return $this->hasMany(WarehouseLocation::class);
    }

    public function childrenRecursive() {
        return $this->locations()->whereColumn('warehouse_id', 'parent_id');
    }

    public function filter_locations() {
        return $this->locations()->whereColumn('warehouse_id', 'parent_id')->where('status',1);
    }
}
