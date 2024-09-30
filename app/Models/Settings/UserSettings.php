<?php

namespace App\Models\Settings;
 
use App\Models\Users\ClientUsers;
use Illuminate\Database\Eloquent\Model;

class UserSettings extends Model
{

    protected $table = 'user_settings';

    protected $guarded = ['id']; 

    public function user()
    {
        return $this->belongsTo(ClientUsers::class,'user_id');
    }
     
}