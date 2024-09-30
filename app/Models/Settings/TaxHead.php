<?php

namespace App\Models\Settings;
 
use App\Models\Users\ClientUsers;
use Illuminate\Database\Eloquent\Model;

class TaxHead extends Model
{

    protected $table = 'tax_heads';

    protected $guarded = ['id']; 

    public function TaxSubtaxes()
    {
        return $this->belongsToMany(TaxHead::class, "tax_subtaxes","tax_head_id","sub_tax")->where('status',1);
    }

    public function TaxHead()
    {
        return $this->belongsToMany(TaxHead::class, "tax_subtaxes","sub_tax","tax_head_id")->where('status',1);
    }

    public function creator()
    {
        return $this->belongsTo(ClientUsers::class, 'created_by');
    }

    public function updator()
    {
        return $this->belongsTo(ClientUsers::class, 'updated_by');
    }
     
}