<?php

namespace App\Models\RosterManagement;

use Illuminate\Database\Eloquent\Model;

class Roster extends Model
{

    protected $table = 'rosters';

    protected $guarded = [
        'id',
    ]; 
    public function dutyShift()
    {
        return $this->belongsTo(DutyShift::class);
    }
}
