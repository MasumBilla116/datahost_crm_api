<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{

    protected $table = 'employee_attendance';
    protected $guarded = [
        'id',
    ];
}
