<?php

namespace App\Models\Website;
 
use App\Models\Website\WebsiteSlider;
use Illuminate\Database\Eloquent\Model;

class WebsiteSlides extends Model
{

    protected $table = 'website_slides';
    protected $guarded = ['id']; 

    public function slider(){
        return $this->belongsTo(WebsiteSlider::class);
    }
     
}