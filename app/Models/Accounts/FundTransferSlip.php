<?php

namespace App\Models\Accounts;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class FundTransferSlip extends Model
{

    protected $table = 'fund_transfer_slips';

    protected $guarded = [
        'id'
    ];

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }

    public function fromAccount()
    {
        return $this->belongsTo(Accounts::class, 'from_account_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(Accounts::class, 'to_account_id');
    }
}