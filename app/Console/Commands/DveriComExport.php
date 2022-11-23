<?php

namespace App\Console\Commands;

use App\Models\DataProduct;
use App\Models\DveriComProduct;
use App\Models\Property;
use App\Models\PropertyValue;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    protected $file;

    private $categories = [
        10 => 'Эко Шпон'
    ];

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
        foreach ($this->categories as $categoryId => $categoryName) {
            $products = DataProduct::query()
                ->whereHas('category', function (Builder $query) use ($categoryId) {
                    $query->where('parent_id', $categoryId);
                })
                ->with([
                    'glass',
                    'color',
                    'trademark',
                    'accessoryGroup',
                    'category' => function (BelongsTo $query) use ($categoryId) {
                        $query->where('parent_id', $categoryId);
                    }
                ])->get();
            $this->file = fopen('eco-shpon.csv', 'w');
            foreach ($products as $product) {
                $this->getProduct($product);
            }
        }
    }

    private function getProduct(DataProduct $dataProduct)
    {
        $product = new DveriComProduct();
        $product->color = $dataProduct->color->title ?? '';
        $product->glass = $dataProduct->glass->title ?? '';
        $product->manufacturer = $dataProduct->trademark->title ?? '';
        $product->description = $this->getProductDescription($dataProduct);
        $product->model = $dataProduct->title;
        $product->image = $this->getPicture($dataProduct);
        $product->name = $this->getProductName($product);
        $this->info($product->name);
        $this->getProductVariants($product, $dataProduct);
    }

    private function getProductDescription(DataProduct $dataProduct): string
    {
        $descriptions = [];
        $description = '';
        foreach ($dataProduct->properties as $property) {
            $propertyTitle = Property::query()->find($property['id']);
            $propertyValue = PropertyValue::query()->find($property['value_id']);
            $descriptions[$propertyTitle->id] = [
                'title' => $propertyTitle->title,
                'value' => $propertyValue->title
            ];
        }
        ksort($descriptions);
        foreach ($descriptions as $item) {
            $description .= '<div class="title">'.$item['title'].':</div>';
            $description .= '<div class="value">'.$item['value'].'</div>';
        }
        return $description;
    }

    private function getProductVariants(DveriComProduct $product, DataProduct $dataProduct)
    {
        foreach ($dataProduct->options as $option) {
            $product->canvasSize = $option['title'];
            $product->artikul = $option['vendor_code'];
            $product->netto = $option['price_dealer'] - $option['price_dealer'] / 100 * $option['discount_dealer'];
            $product->price = $option['price'] - $option['price'] / 100 * $option['discount'];
            $product->exportCsv($this->file);
        }
    }

    private function getPicture(DataProduct $dataProduct)
    {
        $dataPictures = $dataProduct->pictures;
        $keys = ['large', 'medium', 'small'];
        foreach ($keys as $key) {
            if (isset($dataPictures[$key])) {
                return $dataPictures[$key];
            }
        }
        return '';
    }

    private function getProductName(DveriComProduct $product)
    {
        $name = 'Межкомнатная дверь '.$product->manufacturer;
        $name .= ' Модель '.$product->model;
        if (!empty($product->color)) {
            $name .= ' / Цвет '.$product->color;
        }
        if (!empty($product->glass)) {
            $name .= ' / Стекло '.$product->glass;
        }
        return $name;
    }
}
