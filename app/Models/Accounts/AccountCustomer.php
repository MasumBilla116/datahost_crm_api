<?php

namespace App\Models\Accounts;

use Illuminate\Database\Eloquent\Model;

class AccountCustomer extends Model
{

    protected $table = 'account_customer';

    protected $guarded = [
        'id'
    ];

}