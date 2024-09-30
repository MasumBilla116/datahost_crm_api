<?php

namespace App\Models\Restaurant;

use App\Models\Customers\Customer;
use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class RestaurantInvoice extends Model
{

    protected $table = 'restaurant_invoices';

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

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function RestaurantTable()
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }
}
