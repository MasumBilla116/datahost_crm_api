<?php

namespace App\Models\Inventory;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class ConsumptionVoucher extends Model
{

    protected $table = 'consumption_vouchers';

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

    public function inventoryCategory()
    {
        return $this->belongsTo(InventoryCategory::class);
    }
}
