<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class DataProduct
 * @package App\Models
 *
 * @property integer id
 * @property string title
 * @property string url
 * @property integer category_id
 * @property integer color_id
 * @property integer glass_id
 * @property integer accessory_group_id
 * @property integer trademark_id
 * @property integer price
 * @property integer price_dealer
 * @property integer discount
 * @property integer discount_dealer
 * @property string label
 * @property string vendor_code
 * @property integer position
 * @property array pictures
 * @property array options
 * @property array properties
 * @property array accessory_properties
 * @property array analogs
 * @property array related_products
 */
class DataProduct extends Model
{
    use HasFactory;

    protected $table = 'products';
    protected $guarded = [];
    protected $casts = [
        'pictures' => 'array',
        'options' => 'array',
        'properties' => 'array',
        'accessory_properties' => 'array',
        'analogs' => 'array',
        'related_products' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function accessoryGroup()
    {
        return $this->belongsTo(AccessoryGroup::class);
    }

    public function trademark()
    {
        return $this->belongsTo(Trademark::class);
    }

    public function glass()
    {
        return $this->belongsTo(Glass::class);
    }


}
