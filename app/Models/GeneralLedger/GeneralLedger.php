<?php

namespace App\Models\GeneralLedger;


use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class GeneralLedger extends Model
{

    protected $table = 'general_ledger';

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