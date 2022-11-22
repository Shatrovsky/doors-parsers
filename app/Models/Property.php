<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Property
 * @package App\Models
 *
 * @property integer id
 * @property string title
 * @property integer position
 * @property boolean is_accessory
 */
class Property extends Model
{
    use HasFactory;

    protected $table = 'properties';
    protected $guarded = [];
}
