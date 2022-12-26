<?php

namespace App\Console\Commands;

use App\Models\VerdaMProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\DomCrawler\Crawler;

class VerdaM extends Command
{
    const URL = 'https://verda-m.ru';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsing:verda';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсинг https://verda-m.ru';

    protected $urls = [
        'https://verda-m.ru/catalog/dveri-oblitsovannye-ekoshponom/',
        'https://verda-m.ru/catalog/dveri-loyard/',
        'https://verda-m.ru/catalog/mezhkomnatnye-dveri-vinil/',
        'https://verda-m.ru/catalog/dveri-emal/',
        'https://verda-m.ru/catalog/dveri-oblitsovannye-shponom/',
        'https://verda-m.ru/catalog/dveri-laminirovannye/',
        'https://verda-m.ru/catalog/metall-dveri/',
        'https://verda-m.ru/catalog/stroitelnye-dveri/',
    ];
    protected $file;
    protected string $category = '';
    protected string $subCategory1 = '';
    protected string $subCategory2 = '';
    protected array $filterTypes = [];
    protected array $filterCanvasSizes = [];
    protected array $filterColors = [];
    protected array $priceList = [];
    protected array $images = [];
    protected string $model;
    private array $modelUrls = [];
    private string $modelUrl;
    protected $count = 0;

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
        $this->getImages();
        $this->file = fopen('Verda2.csv', 'w');
        fputcsv($this->file, VerdaMProduct::$headers, "\t");
        foreach ($this->urls as $url) {
            $page = 1;
            $load = true;
            $this->modelUrls = [];
            while ($load) {
                $categoryUrl = $url.'?PAGEN_1='.$page;
                $this->error($categoryUrl);
                $html = file_get_contents($categoryUrl);
                $crawler = new Crawler($html);
                $this->category = $crawler->filter('h1')->text();
                $load = $this->getModelUrls($crawler);
                $page++;
            }
            foreach ($this->modelUrls as $modelUrl => $model) {
                $html = file_get_contents(self::URL.$modelUrl);
                $this->modelUrl = $modelUrl;
                $this->model = $model;
                $crawler = new Crawler($html);
                $this->getProduct($crawler);
                $this->getCategories($crawler);
                $this->getPriceList($crawler);
//                dd($this->priceList);
                $this->getFilters($crawler);
                $this->getProduct($crawler);
            }
        }
    }

    private function getModelUrls(Crawler $crawler)
    {
        $modelNodes = $crawler->filter('a.item-catalog');
        foreach ($modelNodes as $modelNode) {
            $modelCrawler = new Crawler($modelNode);
            $url = $modelCrawler->attr('href');
            if (array_key_exists($url, $this->modelUrls)) {
                return false;
            }
            $model = $modelCrawler->filter('span.cat-title')->text();
            $this->modelUrls[$url] = $model;
        }

        return true;
    }

    private function getFilters(Crawler $crawler)
    {
        $nodes = $crawler->filter('div.card-section-wrap');
        foreach ($nodes as $node) {
            $filterCrawler = new Crawler($node);
            $titleNode = $filterCrawler->filter('div.section-title');
            if (count($titleNode) == 0) {
                continue;
            }
            switch ($titleNode->text()) {
                case 'Цвет:':
                    $filterNodes = $filterCrawler->filter('div.frm-select-color');
                    $this->getFilterColors($filterNodes);
                    break;
                /*                case 'Тип:':
                                    $filterNodes = $filterCrawler->filter('div.frm-select-parameter');
                                    $this->getFilterTypes($filterNodes);
                                    break;
                                case 'Размер:':
                                    $filterNodes = $filterCrawler->filter('div.frm-select-parameter');
                                    $this->getFilterCanvasSizes($filterNodes);
                                    break;*/
                default:
                    break;
            }
        }
    }

    private function getFilterTypes($nodes)
    {
        $this->filterTypes = [];
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $this->filterTypes[] = $crawler->text();
        }
    }

    private function getFilterColors($nodes)
    {
        $this->filterColors = [];
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $id = $crawler->attr('data-color');
            $name = $crawler->filter('label')->attr('title');
            $this->filterColors[$id] = ['name' => $name];
        }
    }

    private function getImages()
    {
        $images = Cache::get('images');
        if (!empty($images)) {
            $this->images = $images;
            return;
        }
        $content = file_get_contents("http://verda-m.ru/export/catalog.xml");
        $crawler = new Crawler($content);
        $images = [];
        $nodes = $crawler->filter('offer');
        foreach ($nodes as $node) {
            $imageCrawler = new Crawler($node);
            $id = $imageCrawler->attr('id');
            $image = $imageCrawler->filter('picture');
            if (count($image) > 0) {
                $images[$id] = $image->text();
            }
        }
        Cache::put('images', $images, now()->addMinutes(10));
        $this->images = $images;
    }

    private function getFilterCanvasSizes($nodes)
    {
        $this->filterCanvasSizes = [];
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $this->filterCanvasSizes[] = $crawler->text();
        }
    }

    private function getCategories(Crawler $crawler)
    {
        $category = $crawler->filter('div.breadcrumbs-box > a')->eq(2);
        if (count($category) > 0) {
            $this->category = $category->text();
        } else {
            $this->category = '';
        }
        $category = $crawler->filter('div.breadcrumbs-box > a')->eq(3);
        if (count($category) > 0) {
            $this->subCategory1 = $category->text();
        } else {
            $this->subCategory1 = '';
        }
        $category = $crawler->filter('div.breadcrumbs-box > a')->eq(4);
        if (count($category) > 0) {
            $this->subCategory2 = $category->text();
        } else {
            $this->subCategory2 = '';
        }
    }

    private function getProduct(Crawler $crawler)
    {
        $product = new VerdaMProduct();
        $product->category = $this->category;
        $product->subCategory1 = $this->subCategory1;
        $product->subCategory2 = $this->subCategory2;
        $product->description = $this->getProductDescription($crawler);
        $product->parsingUrl = self::URL.$this->modelUrl;
        $product->model = $this->model;
        $product->name = $this->getProductName($crawler);
        $this->getProductVariants($product);
    }

    private function getPriceList(Crawler $crawler)
    {
        $priceList = [];
        $priceListNodes = $crawler->filter('div.js-offer');
        foreach ($priceListNodes as $priceListNode) {
            $price = [
                'id' => '',
                'color_id' => '',
                'type' => '',
                'canvas_size' => '',
                'price' => 0,
                'image' => ''
            ];
            $priceCrawler = new Crawler($priceListNode);
            $price['id'] = $priceCrawler->attr('data-id') ?? '';
            $price['color_id'] = $priceCrawler->attr('data-color') ?? '';
            $price['type'] = $priceCrawler->attr('data-type') ?? '';
            $price['canvas_size'] = !empty($priceCrawler->attr('data-size')) ? $priceCrawler->attr('data-size') : '';
            $price['price'] = !empty($priceCrawler->attr('data-price')) ? $priceCrawler->attr('data-price') : 1;
            $price['image'] = $this->images[$price['id']] ?? '';
            $priceList[] = $price;
        }
        $this->priceList = $priceList;
    }

    private function getProductDescription(Crawler $crawler)
    {
        $description = $crawler->filter('div.info-inner-wrap')->outerHtml();
        return $description;
    }

    private function getProductVariants(VerdaMProduct $product)
    {
        $mainName = $product->name;
        foreach ($this->priceList as $price) {
            if (!isset($this->filterColors[$price['color_id']])) {
                continue;
            }
            $product->price = $price['price'];
            if ($product->price == 0) {
                $product->price = 1;
            }
            $product->canvasSize = $price['canvas_size'];
            if ($product->canvasSize == 'empty') {
                $product->canvasSize = '';
            }
            if (strpos($price['type'], 'лухо') === false) {
                $product->glass = $price['type'];
            } else {
                $product->glass = '';
            }
            $product->color = $this->filterColors[$price['color_id']]['name'];
            $product->image = $this->images[$price['id']] ?? '';
            $product->name = $this->getProductVariantName($product, $mainName);
            $product->metaTitle = $product->metaKeywords = $product->metaDescription = $product->name;
            $product->supplierArticul = $price['id'];
            $product->setInnerArticul();
            $this->info($product->category.' - '.$product->subCategory1.' - '.$product->subCategory2.
                ' - '.$product->name);
            $product->exportCsv($this->file);
        }
    }

    private function getProductName(Crawler $crawler)
    {
        $name = $crawler->filter('h1')->text();
        return $name;
    }

    private function getProductVariantName(VerdaMProduct $product, string $mainName)
    {
        $name = $mainName;
        if (!empty($product->color)) {
            $name .= ' / Цвет '.$product->color;
        }
        if (!empty($product->glass)) {
            $name .= ' / '.$product->glass;
        }
        $name .= ' / '.$product->manufacturer;

        return $name;
    }

}

