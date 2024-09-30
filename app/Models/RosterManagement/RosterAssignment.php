<?php

namespace App\Models\RosterManagement;

use Illuminate\Database\Eloquent\Model;

class RosterAssignment extends Model
{

    protected $table = 'roster_assignments';

    protected $guarded = [
        'id',
    ]; 
    public function rosterEmployee()
    {
        return $this->hasMany(RosterEmployee::class);
    }
    public function roster()
    {
        return $this->belongsTo(Roster::class);
    }
}