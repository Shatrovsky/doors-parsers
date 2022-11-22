<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Trademark
 * @package App\Models
 *
 * @property integer id
 * @property string title
 * @property integer position
 */
class Trademark extends Model
{
    use HasFactory;

    protected $table = 'trademarks';
    protected $guarded = [];
}
