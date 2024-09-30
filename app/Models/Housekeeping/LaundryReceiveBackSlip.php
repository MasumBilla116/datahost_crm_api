<?php

namespace App\Models\Housekeeping;


use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class LaundryReceiveBackSlip extends Model
{

    protected $table = 'laundry_receive_back_slips';

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

    public function rcv_back_list()
    {
        return $this->hasMany(LaundryReceiveBackSlipItems::class);
    }
}