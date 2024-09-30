<?php

namespace App\Models\Website;
 
use Illuminate\Database\Eloquent\Model;

class WebsitePage extends Model
{

    protected $table = 'website_page_config';

    protected $guarded = ['id']; 
    public function pageDetails()
    {
        return $this->hasMany(WebsitePageDetails::class,'website_page_config_id','id');
    }
    public function template()
    {
        return $this->belongsTo(WebsiteTemplate::class);
    }
    public function menu()
    {
        return $this->belongsTo(WebsiteMenu::class);
    }

     
}