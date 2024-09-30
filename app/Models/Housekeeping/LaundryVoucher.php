<?php

namespace App\Models\Housekeeping;

use App\Models\Users\ClientUsers;
use App\Models\Housekeeping\LaundryVoucherItem;

use Illuminate\Database\Eloquent\Model;

class LaundryVoucher extends Model
{

    protected $table = 'laundry_vouchers';

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
    
    public function user()
    {
        return $this->belongsTo(ClientUsers::class);
    }

    public function voucher_list()
    {
        return $this->hasMany(LaundryVoucherItem::class);
    }
}