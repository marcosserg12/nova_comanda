<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyCounter extends Model
{
    protected $primaryKey = 'business_date';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['business_date', 'last_number'];
}
