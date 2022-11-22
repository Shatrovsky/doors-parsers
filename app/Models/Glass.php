<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Glass
 * @package App\Models
 *
 * @property integer id
 * @property string title
 */
class Glass extends Model
{
    use HasFactory;

    protected $table = 'glasses';
    protected $guarded = [];
}
