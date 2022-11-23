<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PropertyValue
 * @package App\Models
 *
 * @property integer id
 * @property integer property_id
 * @property integer product_id
 * @property string title
 */
class PropertyValue extends Model
{
    use HasFactory;

    protected $table = 'property_values';
    protected $guarded = [];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
