<?php

namespace App\Models\Transport;


use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class Drivers extends Model
{

    protected $table = 'drivers';

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