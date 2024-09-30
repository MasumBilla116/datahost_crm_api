<?php

namespace App\Models\Purchase;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inventory\InventoryCategory;

class InvoiceItem extends Model
{

    protected $table = 'supplier_invoice_item';
    public $timestamps = false; //To ignore updated_at

    protected $guarded = [
        'id'
    ];

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }
    
    public function category(){
        return $this->belongsTo(InventoryCategory::class);
    }
}