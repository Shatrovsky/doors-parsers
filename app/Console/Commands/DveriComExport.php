<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\DataProduct;
use App\Models\DveriComProduct;
use App\Models\Property;
use App\Models\PropertyValue;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\DomCrawler\Crawler;

class DveriComExport extends Command
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
    protected int $count = 0;
    protected int $number = 0;
    protected $excludeCategories = [
        'Porta'
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
                        if (in_array($subCategory2->title, $this->excludeCategories)) {
                            continue;
                        }
                        $this->subCategory2 = $subCategory2;
                        $this->renameSubcategory2();
                        $this->getProducts($subCategory2->id);
                    }
                }
            }
        }
    }

    private function renameSubcategory2()
    {
        $map = [
            'Ручки' => 'Ручка дверная',
            'Накладки' => 'Накладка под цилиндр',
            'Фиксаторы' => 'Фиксатор',
            'Ручки-защелки' => 'Ручка-защелка',
            'Петли' => 'Петля дверная',
            'Замки' => 'Замок',
            'Защелки' => 'Защелка',
            'Цилиндры' => 'Цилиндр',
            'Шпингалеты' => 'Шпингалет',
        ];
        if (isset($map[$this->subCategory2->title])) {
            $this->subCategory2->title = $map[$this->subCategory2->title];
        }
    }
    private function getProducts(int $categoryId)
    {
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
        $product->manufacturer = 'Двери Браво';
        $product->model = $dataProduct->title;
        $product->image = $this->getPicture($dataProduct);
        $product->name = $this->getProductName($product);
        $product->metaTitle = $product->name;
        $product->metaKeywords = $product->name;
        $product->metaDescription = $product->name;
        $product->parsingUrl = $dataProduct->url;
//        $product->description = $this->getProductDescription($dataProduct);
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
        $excludes = ['правое', 'с усилением', 'защелки'];
        if (!empty($dataProduct->options) && $this->category->id != 106) {
            foreach ($dataProduct->options as $option) {
                $missing = false;
                foreach ($excludes as $exclude) {
                    if (strpos($option['title'], $exclude) !== false) {
                        $missing = true;
                        break;
                    }
                }
                if ($missing == true) {
                    continue;
                }
                $option['title'] = str_replace('левое', '', $option['title']);
                $option['title'] = str_replace('без усиления ', '', $option['title']);
                $product->canvasSize = $option['title'];
                $product->artikul = $option['vendor_code'];
                if (!empty($option['price_dealer'])) {
                    $product->netto = $option['price_dealer'] - $option['price_dealer'] / 100 * $option['discount_dealer'];
                    $product->price = $product->netto * 1.3 - 4;
                }
                $product->parsingUrl = $dataProduct->url.'?option_id='.$option['id'];
                try {
                    $product->description = $this->getDescriptionHtml($product->parsingUrl);
                } catch (\Exception $exception) {
                    $this->error($exception->getMessage());
                    return;
                }
                $this->info($product->category." - ".$product->subCategory1.' - '.$product->subCategory2.' - '.$product->name." ".$product->artikul);
                $product->exportCsv($this->file);
            }
        } else {
            $product->parsingUrl = $dataProduct->url;
            $product->artikul = $dataProduct->vendor_code;
            if (!empty($dataProduct->price_dealer)) {
                $product->netto = $dataProduct->price_dealer - $dataProduct->price_dealer / 100 * $dataProduct->discount_dealer;
                $product->price = $product->netto * 1.3 - 4;
            }
            try {
                $product->description = $this->getDescriptionHtml($product->parsingUrl);
            } catch (\Exception $exception) {
                $this->error($exception->getMessage());
                return;
            }
            $this->info($product->category." - ".$product->subCategory1.' - '.$product->subCategory2.' - '.$product->name." ".$product->artikul);
            $product->exportCsv($this->file);
        }
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
        } elseif (in_array($product->category, ['Плинтус', 'Фурнитура и прочее'])) {
            $name = '';
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
        $categories = Category::query()->whereNull('parent_id')->whereIn('id', [300])->get(['id', 'title']);
        return $categories;
    }

    private function getSubCategories(int $id): Collection
    {
        $excludeCategories = [
            'Для стеклянных дверей',
            'Для входных дверей',
            'Цифры'
        ];
        $categories = Category::query()->where('parent_id', $id)->where('id', 304)->whereNotIn('title',
            $excludeCategories)->get([
            'id', 'title'
        ]);
        return $categories;
    }

    private function getDescriptionHtml(string $url)
    {
        $description = '';
        $html = file_get_contents($url);
        $crawler = new Crawler($html);
        $accessories = $crawler->filter('table.product__table.product__table--components');
        if (count($accessories) > 0) {
            $accessories = $accessories->outerHtml();
            $accessories = preg_replace('|(<td class="product__table--td hidden-closed">)(.*)(</td>)|Uis', '',
                $accessories);
            $accessories = preg_replace('|(<td class="hidden-closed">)(.*)(</td>)|Uis', '', $accessories);
            $accessories = str_replace('cellpadding="0" cellspacing="0"', 'cellpadding="5" cellspacing="5"',
                $accessories);
            $accessories = str_replace('/storage', 'https://dveri.com/storage', $accessories);
            $description .= '<div><b>Комлектующие</b></div>'.$accessories;
        }

        $mainNode = $crawler->filter('li.tabs__content-item')->first();
        $mainNodeCrawler = new Crawler($mainNode->html());
        $mainItems = $mainNodeCrawler->filter('div.product__property-list');
        if (!empty($mainItems)) {
            if (!empty($description)) {
                $description .= '<p></p>';
            }
            foreach ($mainItems as $mainItem) {
                $mainCrawler = new Crawler($mainItem);
                $mainDescription = $mainCrawler->html();
                $mainDescription = preg_replace('|(<div class="product__property-name">)(.*)(</div>)|Uis',
                    '$1<b>$2</b>$3', $mainDescription);
                $description .= $mainDescription;
            }
        }
        return $description;
    }
}
