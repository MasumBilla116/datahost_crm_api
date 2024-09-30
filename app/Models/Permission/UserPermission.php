<?php

namespace App\Models\Permission;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class UserPermission extends Model
{

    protected $table = 'user_permission';

    protected $fillable = [
        'access_title',
        'modules',
        'description',
        'email',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
        'status',
    ];

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }
}
