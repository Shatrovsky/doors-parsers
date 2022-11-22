<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class DataAttributeValue
 * @package App\Models
 *
 * @property integer id
 * @property integer attribute_id
 * @property string title
 * @property string generation_title
 * @property string description
 * @property integer position
 */
class DataAttributeValue extends Model
{
    use HasFactory;

    protected $table = 'attribute_values';
    protected $guarded = [];
}
