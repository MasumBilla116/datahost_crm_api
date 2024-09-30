<?php

namespace App\Models\HRM;

use App\Models\HRM\Employee;

use App\Models\Users\ClientUsers;
use Illuminate\Database\Eloquent\Model;

class Departments extends Model
{

    protected $table = 'departments';

    protected $fillable = [
        'name',
        'description',
        'company',
        'clientID',
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
    
    public function employees(){
        return $this->hasMany(Employee::class);
    }
}
