<?php

namespace App\Models\Website;
 
use Illuminate\Database\Eloquent\Model;

class WebsitePageDetails extends Model
{

    protected $table = 'website_page_config_details';

    protected $guarded = ['id']; 

    public function getSettingsValueAttribute($value)
    {
        return json_decode($value);
    }
}