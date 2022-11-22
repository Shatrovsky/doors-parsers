<?php

namespace App\Console\Commands;

use App\Models\Accessory;
use App\Models\AccessoryGroup;
use App\Models\Category;
use App\Models\Color;
use App\Models\ColorGroup;
use App\Models\DataAttribute;
use App\Models\DataAttributeValue;
use App\Models\DataProduct;
use App\Models\Glass;
use App\Models\Product;
use App\Models\Property;
use App\Models\PropertyValue;
use App\Models\Trademark;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DveriComLoader extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsing:dveri-com';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсинг https://dveri.com/';
    protected $data;
    protected $categories = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->truncateTables();
        $this->data = $this->getContent();
        $this->setColorGroups();
        $this->setColors();
        $this->setCategories();
        $this->setGlasses();
        $this->setTrademarks();
        $this->setDataAttributes();
        $this->setDataAttributeValues();
        $this->setDataProperties();
        $this->setDataPropertyValues();
        $this->setAccessoryGroups();
        $this->setAccessories();
        $this->setProducts();
    }

    private function getContent()
    {
        $source = 'app/data/moskva.json';
        $data = file_get_contents($source);
        return json_decode($data, true);
    }

    private function setCategories()
    {
        foreach ($this->data['categories'] as $dataCategory) {
            $category = new Category($dataCategory);
            $category->saveOrFail();
        }
    }

    private function setGlasses()
    {
        foreach ($this->data['glasses'] as $dataGlass) {
            $glass = new Glass($dataGlass);
            $glass->saveOrFail();
        }
    }

    private function setTrademarks()
    {
        foreach ($this->data['trademarks'] as $dataTrademark) {
            $trademark = new Trademark($dataTrademark);
            $trademark->saveOrFail();
        }
    }

    private function setDataAttributes()
    {
        foreach ($this->data['attributes'] as $dataAttribute) {
            $attribute = new DataAttribute($dataAttribute);
            $attribute->saveOrFail();
        }
    }

    private function setDataAttributeValues()
    {
        foreach ($this->data['attributeValues'] as $dataAttributeValue) {
            $attributeValue = new DataAttributeValue($dataAttributeValue);
            $attributeValue->saveOrFail();
        }
    }

    private function setAccessoryGroups()
    {
        foreach ($this->data['accessory_groups'] as $dataAccessoryGroup) {
            $accessoryGroup = new AccessoryGroup($dataAccessoryGroup);
            $accessoryGroup->saveOrFail();
        }
    }

    private function setAccessories()
    {
        foreach ($this->data['accessories'] as $dataAccessory) {
            $accessory = new Accessory($dataAccessory);
            $accessory->saveOrFail();
        }
    }

    private function setColorGroups()
    {
        foreach ($this->data['color_groups'] as $colorGroup) {
            $group = new ColorGroup($colorGroup);
            $group->saveOrFail();
        }
    }

    private function setColors()
    {
        foreach ($this->data['colors'] as $color) {
            $color = new Color($color);
            $color->saveOrFail();
        }
    }

    private function setProducts()
    {
        foreach ($this->data['products'] as $dataProguct) {
            $product = new DataProduct($dataProguct);
            $product->saveOrFail();
        }
    }

    private function setDataProperties()
    {
        foreach ($this->data['properties'] as $dataProperty) {
            $property = new Property($dataProperty);
            $property->saveOrFail();
        }
    }

    private function setDataPropertyValues()
    {
        foreach ($this->data['property_values'] as $dataPropertyValue) {
            $propertyValue = new PropertyValue($dataPropertyValue);
            $propertyValue->saveOrFail();
        }
    }

    private function truncateTables()
    {
        DB::table('categories')->truncate();
        DB::table('glasses')->truncate();
        DB::table('trademarks')->truncate();
        DB::table('attributes')->truncate();
        DB::table('attribute_values')->truncate();
        DB::table('accessory_groups')->truncate();
        DB::table('accessories')->truncate();
        DB::table('properties')->truncate();
        DB::table('property_values')->truncate();
        DB::table('color_groups')->truncate();
        DB::table('colors')->truncate();
        DB::table('products')->truncate();
    }
}
