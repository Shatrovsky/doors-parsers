<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class DataAttribute
 * @package App\Models
 *
 * @property integer id
 * @property string title
 */
class DataAttribute extends Model
{
    use HasFactory;

    protected $table = 'attributes';
    protected $guarded = [];
}
