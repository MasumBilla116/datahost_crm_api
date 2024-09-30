<?php

namespace App\Models\Permission;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{

    protected $table = 'role_permission';

    protected $guarded = [
        'id'
    ];
}
