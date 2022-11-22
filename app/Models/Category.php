<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Category
 * @package App\Models
 *
 * @property integer id
 * @property integer parent_id
 * @property string title
 * @property integer lft
 * @property integer rgt
 */
class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';
    protected $guarded = [];
}
