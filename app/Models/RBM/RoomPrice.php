<?php

namespace App\Models\RBM;

use App\Models\RBM\RoomType;
use Illuminate\Database\Eloquent\Model;

class RoomPrice extends Model
{

    protected $table = 'room_price';

    protected $guarded = [
        'id',
    ];

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }


}
