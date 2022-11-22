<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AccessoryGroup
 * @package App\Models
 *
 * @property integer id
 * @property string title
 */
class AccessoryGroup extends Model
{
    use HasFactory;

    protected $table = 'accessory_groups';
    protected $guarded = [];
}
