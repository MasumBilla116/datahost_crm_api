<?php

namespace App\Models\Inventory;

use App\Models\Users\ClientUsers;

use App\Models\Inventory\InventoryItem;
use Illuminate\Database\Eloquent\Model;

class InventoryCategory extends Model
{

    protected $table = 'inventory_categories';

    protected $guarded = [
        'id',
    ];

    public function parent()
    {
        return $this->belongsTo(InventoryCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(InventoryCategory::class, 'parent_id');
    }

    public function childrenRecursive()
    {
        return $this->children()->where('status',1)->with('childrenRecursive');
    }

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }

    public function updator()
    {
        return $this->belongsTo(ClientUsers::class, 'updated_by');
    }

    public function items()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function supplierInvoiceItems(){
        return $this->hasMany(InventoryItem::class);
    }
}