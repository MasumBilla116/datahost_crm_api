<?php

namespace App\Models\HouseKeeping;


use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class HouseKeepingSlip extends Model
{

    protected $table = 'housekeeping_slip';

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
}