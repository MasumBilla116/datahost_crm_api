<?php

namespace App\Models\RosterManagement;

use App\Models\HRM\Employee;
use Illuminate\Database\Eloquent\Model;

class RosterEmployee extends Model
{

    protected $table = 'roster_employees';

    protected $guarded = [
        'id',
    ]; 
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}