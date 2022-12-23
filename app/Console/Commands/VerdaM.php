<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProfilProduct;
use App\Models\VerdaMProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\DomCrawler\Crawler;
use function Symfony\Component\DomCrawler\text;

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
        'https://verda-m.ru/catalog/dveri-loyard/'
    ];
    protected $file;
    protected string $category = '';
    protected string $subCategory1 = '';
    protected string $subCategory2 = '';
    protected array $filterTypes = [];
    protected array $filterCanvasSizes = [];
    protected array $filterColors = [];
    protected array $priceList = [];
    private array $modelUrls = [
        'https://verda-m.ru/catalog/dveri-oblitsovannye-ekoshponom/dveri-skin-ekoshpon/geometriya/k-11/'
    ];
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
        $page = 1;
        foreach ($this->urls as $url) {
            $load = true;
            /*            while ($load){
                            $categoryUrl = $url . '?PAGEN_1=' . $page;
                            $this->error($categoryUrl);
                            $html = file_get_contents($categoryUrl);
                            $crawler = new Crawler($html);
                            $this->category = $crawler->filter('h1')->text();
                            $load = $this->getModelUrls($crawler);
                            $page++;
                        }*/
            $this->file = fopen('Межкомнатные двери экошпон.csv', 'w');
            fputcsv($this->file, VerdaMProduct::$headers, "\t");
            foreach ($this->modelUrls as $modelUrl) {
                $html = file_get_contents($modelUrl);
                $this->modelUrl = $modelUrl;
                $crawler = new Crawler($html);
                $this->getProduct($crawler);
                $this->getCategories($crawler);
                $this->getPriceList($crawler);
//                dd($this->priceList);
                $this->getFilters($crawler);
                $this->getProduct($crawler);
                dd($this->filterColors, $this->filterTypes, $this->filterCanvasSizes);
                exit;
            }
        }
    }

    private function getModelUrls(Crawler $crawler)
    {
        $modelNodes = $crawler->filter('a.item-catalog');
        foreach ($modelNodes as $modelNode) {
            $modelCrawler = new Crawler($modelNode);
            $url = $modelCrawler->attr('href');
//            $this->info($url);
            if (in_array($url, $this->modelUrls)) {
                return false;
            }
            $this->modelUrls[] = $url;
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
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $this->filterTypes[] = $crawler->text();
        }
    }

    private function getFilterColors($nodes)
    {
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $id = $crawler->attr('data-color');
            $name = $crawler->filter('img')->attr('alt');
            $image = $crawler->filter('img')->attr('src');
            $this->filterColors[$id] = ['name' => $name, 'image' => $image];
        }
    }

    private function getFilterCanvasSizes($nodes)
    {
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
        $product->parsingUrl = $this->modelUrl;
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
                'price' => 0
            ];
            $priceCrawler = new Crawler($priceListNode);
            $price['id'] = $priceCrawler->attr('data-id') ?? '';
            $price['color_id'] = $priceCrawler->attr('data-color') ?? '';
            $price['type'] = $priceCrawler->attr('data-type') ?? '';
            $price['canvas_size'] = $priceCrawler->attr('data-size') ?? '';
            $price['price'] = $priceCrawler->attr('data-price') ?? '';
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
            $product->canvasSize = $price['canvas_size'];
            $product->glass = $price['type'] != 'Глухое' ? $price['type'] : '';
            $product->color = $this->filterColors[$price['color_id']]['name'];
            $product->name = $this->getProductVariantName($product, $mainName);
            $product->metaTitle = $product->metaKeywords = $product->metaDescription = $product->name;
            $product->supplierArticul = $price['id'];
            $product->setInnerArticul();

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
            $name .= ' / Стекло '.$product->glass;
        }

        
        return $name;
    }

}

