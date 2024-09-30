<?php

namespace App\Models\Accounts;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class Accounts extends Model
{

    protected $table = 'accounts';

    protected $guarded = [
        'id'
    ];

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }
}