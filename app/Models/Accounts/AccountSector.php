<?php

namespace App\Models\Accounts;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class AccountSector extends Model
{

    protected $table = 'account_sectors';

    protected $guarded = [
        'id'
    ];

    public function parent()
    {
        return $this->belongsTo(AccountSector::class, 'parent_id');
    }
    
    public function children()
    {
        return $this->hasMany(AccountSector::class, 'parent_id');
    }

    public function childrenRecursive()
    {
        return $this->children()->where('status',1)->with('childrenRecursive');
    }

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }
}