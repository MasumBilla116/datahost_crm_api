<?php

namespace App\Models\Inventory;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class WarehouseLocation extends Model
{

    protected $table = 'warehouse_locations';

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

    public function parent()
    {
        return $this->belongsTo(WarehouseLocation::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(WarehouseLocation::class, 'parent_id');
    }

    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

    public function parentRecursive()
    {
        return $this->parent()->where('status',1)->with('parentRecursive');
    }

    public function warehouseLevel()
    {
        return $this->belongsTo(WarehouseLevel::class);
    }
}
