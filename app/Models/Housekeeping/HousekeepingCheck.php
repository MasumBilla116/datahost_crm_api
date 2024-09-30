<?php

namespace App\Models\Housekeeping;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;
use App\Models\Housekeeping\HousekeeperTask;


class HousekeepingCheck extends Model
{

    protected $table = 'housekeeping_checklist';

    protected $guarded = [
        'id',
    ];

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }

    public function updator()
    {
        return $this->belongsTo(ClientUsers::class, 'updated_by');
    }

    public function housekeeperTask()
    {
        return $this->belongsTo(HousekeeperTask::class);
    }


}
