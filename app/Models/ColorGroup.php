<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ColorGroup
 * @package App\Models
 *
 * @property integer id
 * @property string title
 * @property integer position
 */
class ColorGroup extends Model
{
    use HasFactory;

    protected $table = 'color_groups';
    protected $guarded = [];
}
