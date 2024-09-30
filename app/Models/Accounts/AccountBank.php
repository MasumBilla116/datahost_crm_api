<?php

namespace App\Models\Accounts;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class AccountBank extends Model
{

    protected $table = 'account_bank';

    protected $guarded = [
        'id'
    ];

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }
}