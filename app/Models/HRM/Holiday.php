<?php

namespace App\Models\HRM;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{

    protected $table = 'holidays';

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
}
