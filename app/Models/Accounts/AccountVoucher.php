<?php

namespace App\Models\Accounts;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class AccountVoucher extends Model
{

    protected $table = 'accounts_voucher';

    protected $guarded = [
        'id'
    ];

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }

    public function account()
    {
        return $this->belongsTo(Accounts::class, 'account_id');
    }
}