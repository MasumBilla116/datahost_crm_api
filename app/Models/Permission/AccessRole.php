<?php

namespace App\Models\Permission;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;
use App\Models\Permission\AccessPermission;

class AccessRole extends Model
{

    protected $table = 'roles';

    protected $guarded = [
        'id'
    ];

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }
    public function users(){
        return $this->hasMany(ClientUsers::class);
    }

    public function permissions(){
        return $this->belongsToMany(AccessPermission::class,'role_permission','role_id','permission_id');
    }
}
