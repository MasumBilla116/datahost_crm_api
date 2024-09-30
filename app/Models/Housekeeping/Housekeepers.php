<?php

namespace App\Models\HouseKeeping;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class Housekeepers extends Model
{

    protected $table = 'housekeepers';

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
