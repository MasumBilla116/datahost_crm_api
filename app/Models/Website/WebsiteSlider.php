<?php

namespace App\Models\Website;
 
use App\Models\Website\WebsiteSlides;
use Illuminate\Database\Eloquent\Model;

class WebsiteSlider extends Model
{

    protected $table = 'website_sliders';
    protected $guarded = ['id']; 


    public function slides(){
        return $this->hasMany(WebsiteSlides::class,'slider_id');
    }
     
}