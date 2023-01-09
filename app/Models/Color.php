<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Color
 * @package App\Models
 *
 * @property integer id
 * @property integer color_group_id
 * @property string picture
 * @property string title
 * @property integer position
 */
class Color extends Model
{
    use HasFactory;

    protected $table = 'colors';
    protected $guarded = ['picture'];
}
