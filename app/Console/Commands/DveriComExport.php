<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\DataProduct;
use App\Models\DveriComProduct;
use App\Models\Property;
use App\Models\PropertyValue;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
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
    protected $file;

    private $category = null;
    private $subCategory1 = null;
    private $subCategory2 = null;

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
        $categories = $this->getCategories();
        foreach ($categories as $category) {
            $this->category = $category;
            $this->file = fopen($this->category->title.'.csv', 'w');
            fputcsv($this->file, DveriComProduct::$headers, "\t");
            $this->subCategory1 = null;
            $subCategories1 = $this->getSubCategories($category->id);
            foreach ($subCategories1 as $subCategory1) {
                $this->subCategory1 = $subCategory1;
                $this->subCategory2 = null;
                $subCategories2 = $this->getSubCategories($subCategory1->id);
                if ($subCategories2->isEmpty()) {
                    $this->getProducts($subCategory1->id);
                } else {
                    foreach ($subCategories2 as $subCategory2) {
                        $this->subCategory2 = $subCategory2;
                        $this->getProducts($subCategory2->id);
                    }
                }
            }
        }
    }

    private function getProducts(int $categoryId)
    {
        DB::enableQueryLog();
        $products = DataProduct::query()
            ->with([
                'glass',
                'color',
                'trademark',
                'accessoryGroup',
            ])
            ->where('category_id', $categoryId)
            ->get();
        foreach ($products as $product) {
            $this->getProduct($product);
        }
    }

    private function getProduct(DataProduct $dataProduct)
    {
        $product = new DveriComProduct();
        $product->category = $this->category->title ?? '';
        $product->subCategory1 = $this->subCategory1->title ?? '';
        $product->subCategory2 = $this->subCategory2->title ?? '';
        $product->color = $dataProduct->color->title ?? '';
        $product->glass = $dataProduct->glass->title ?? '';
        $product->manufacturer = $dataProduct->trademark->title ?? '';
        $product->description = $this->getProductDescription($dataProduct);
        $product->model = $dataProduct->title;
        $product->image = $this->getPicture($dataProduct);
        $product->name = $this->getProductName($product);
        $product->parsingUrl = $dataProduct->url;
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
            $product->price = $product->netto * 1.3 - 4;
            $product->exportCsv($this->file);
        }
        exit;
    }

    private function getPicture(DataProduct $dataProduct)
    {
        $dataPictures = $dataProduct->pictures;
        $keys = ['large', 'medium', 'small'];
        $images = [];
        foreach ($dataPictures as $dataPicture) {
            foreach ($keys as $key) {
                if (isset($dataPicture[$key])) {
                    $images[] = $dataPicture[$key];
                    break;
                }
            }
        }
        return implode(' ', $images);
    }

    private function getProductName(DveriComProduct $product)
    {
        if (strpos($product->category, 'двери')) {
            $name = 'Дверь';
        } else {
            $name = $product->category;
        }

        $name .= ' '.$product->model;
        if (!empty($product->color)) {
            $name .= ' / Цвет '.$product->color;
        }
        if (!empty($product->glass)) {
            $name .= ' / Стекло '.$product->glass;
        }
        $name .= ' / '.$product->manufacturer;
        return $name;
    }

    private function getCategories(): Collection
    {
        $categories = Category::query()->whereNull('parent_id')->limit(1)->get(['id', 'title']);
        return $categories;
    }

    private function getSubCategories(int $id): Collection
    {
        $categories = Category::query()->where('parent_id', $id)->get(['id', 'title']);
        return $categories;
    }
}
