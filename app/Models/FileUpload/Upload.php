<?php

namespace App\Models\FileUpload;

use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{

    protected $table = 'uploads';

    protected $guarded = [
        'id',
    ];


}
