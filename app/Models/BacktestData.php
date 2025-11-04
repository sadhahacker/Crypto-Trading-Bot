<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BacktestData extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'timeframe',
        'from_date',
        'to_date',
        'file_path',
        'file_name',
        'file_size',
    ];
}
