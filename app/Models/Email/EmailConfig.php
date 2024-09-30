<?php

namespace App\Models\Email;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class EmailConfig extends Model
{

    protected $table = 'config_email';

    protected $guarded = [
        'id'
    ];

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }
}