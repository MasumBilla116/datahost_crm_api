<?php

namespace App\Models\HRM;

use App\Models\Users\ClientUsers;

use Illuminate\Database\Eloquent\Model;

class Salary extends Model
{

    protected $table = 'salary';

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

    public function leaveCategory()
    {
        return $this->belongsTo(LeaveCategory::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
