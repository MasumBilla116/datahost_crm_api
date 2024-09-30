<?php

namespace App\Models\Housekeeping;



use App\Models\Inventory\InventoryItem;
use Illuminate\Database\Eloquent\Model;

class LaundryReceiveBackSlipItems extends Model
{

    protected $table = 'laundry_receive_back_slip_items';

    protected $guarded = [
        'id',
    ];


    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }



    
}
