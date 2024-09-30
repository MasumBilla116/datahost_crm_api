<?php

namespace App\Models\Housekeeping;



use App\Models\Inventory\InventoryItem;
use Illuminate\Database\Eloquent\Model;

class LaundryVoucherItem extends Model
{

    protected $table = 'laundry_voucher_items';

    protected $guarded = [
        'id',
    ];


    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }



    
}
