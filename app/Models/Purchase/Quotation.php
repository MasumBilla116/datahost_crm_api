<?php

namespace App\Models\Purchase;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{

    protected $table = 'quotation';
    public $timestamps = false; //To ignore updated_at

    protected $guarded = [
        'id'
    ];

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }
}
