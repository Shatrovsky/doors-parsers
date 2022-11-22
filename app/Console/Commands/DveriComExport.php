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
    protected $signature = 'export:dveri-com';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Экспорт данных с https://dveri.com/';
    private $categories = [507];

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
        foreach ($this->categories as $categoryId) {
            $product = DataProduct::query()->with([
                'category', 'glass', 'color', 'trademark', 'accessoryGroup'
            ])->where('category_id', $categoryId)->first();
            $this->getProduct($product);
        }
    }

    private function getProduct(DataProduct $dataProduct)
    {
        $product = new Product();
    }
}
