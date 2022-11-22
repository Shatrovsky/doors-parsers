<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Accessory
 * @package App\Models
 *
 * @property integer id
 * @property integer accessory_group_id
 * @property string title
 * @property float quantity
 * @property integer price
 * @property integer price_dealer
 * @property integer discount
 * @property integer discount_dealer
 * @property string label
 * @property string vendor_code
 * @property array pictures
 */
class Accessory extends Model
{
    use HasFactory;

    protected $table = 'accessories';
    protected $guarded = [];
    protected $casts = [
        'pictures' => 'array'
    ];

}
