<?php

namespace App\Models\HRM;

use App\Models\HRM\Departments;
use App\Models\HRM\Designation;
use App\Models\Users\ClientUsers;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{

    protected $table = 'employees';

    protected $guarded = ['id'];

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }

    public function department()
    {
        return $this->belongsTo(Departments::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }
    public function user(){
        return $this->belongsTo(ClientUsers::class);
    }

    public function leaveApplication()
    {
        return $this->hasMany(LeaveApplication::class);
    }

}