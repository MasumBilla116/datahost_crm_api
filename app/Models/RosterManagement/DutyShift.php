<?php

namespace App\Models\RosterManagement;

use Illuminate\Database\Eloquent\Model;

class DutyShift extends Model
{

    protected $table = 'duty_shifts';

    protected $guarded = [
        'id',
    ]; 
}
