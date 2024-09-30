<?php

namespace App\Models\Settings;
 
use App\Models\Users\ClientUsers;
use Illuminate\Database\Eloquent\Model;

class ServiceCharge extends Model
{

    protected $table = 'service_charges';

    protected $guarded = ['id']; 

    public function updator()
    {
        return $this->belongsTo(ClientUsers::class, 'updated_by');
    }
     
}