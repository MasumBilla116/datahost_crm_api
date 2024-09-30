<?php

namespace App\Models\Users;

use App\Models\HRM\City;
use App\Models\HRM\Country;
use App\Models\HRM\Employee;
use App\Models\Housekeeping\Housekeepers;
use App\Models\HRM\State;
use App\Models\Permission\AccessRole;
use App\Models\RBM\Tower;
use App\Models\Settings\UserSettings;
use Illuminate\Database\Eloquent\Model;

class ClientUsers extends Model
{

    protected $table = 'org_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'company',
        'clientID',
        'role_id',
        'address',
        'gender',
        'nationality',
        'id_type',
        'zip_code',
        'state_id',
        'city_id',
        'country_id',
        'state',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
        'status',
        'verified',
        'phone_verified',
        'data_access_type'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
    public function housekeeper()
    {
        return $this->belongsTo(Housekeepers::class);
    }
    public function role()
    {
        return $this->belongsTo(AccessRole::class);
    }
    public function permissions()
    {
        return $this->hasMany(AccessPermission::class);
    }
    public function country()
    {
        return $this->belongsTo(Country::class);
    }
    public function state()
    {
        return $this->belongsTo(State::class);
    }
    public function city()
    {
        return $this->belongsTo(City::class);
    }
    public function towers()
    {
        return $this->hasMany(Tower::class);
    }

    public function settings()
    {
        return $this->hasMany(UserSettings::class, 'user_id');
    }
}
