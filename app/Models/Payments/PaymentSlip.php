<?php

namespace App\Models\Payments;
use Illuminate\Database\Eloquent\Model;

class PaymentSlip extends Model
{

    protected $table = 'payment_collection_slip';

    protected $guarded = ['id'];

}