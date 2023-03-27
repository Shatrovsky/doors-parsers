<?php

namespace App\Console\Commands;

use App\Models\BelwoodDoorsProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\DomCrawler\Crawler;

class BelwoodDoorsPriceUpdate extends Command
{
    const URL = 'https://belwooddoors.ru';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'price-update:belwooddoors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсинг https://belwooddoors.ru';

    protected $urls = [
        'https://belwooddoors.ru/catalog/mezhkomnatnye_dveri/',
    ];
    protected $file;
    protected string $category = '';
    protected string $subCategory1 = '';
    protected string $subCategory2 = '';
    private string $modelUrl = '';
    private array $filterCanvasSizes = [];
    private array $filterColors = [];
    private array $filterGlasses = [];
    private array $modelImages = [];
    private array $offers = [];
    private array $prices = [];
    private string $manufacturer;
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
        $priceList = fopen('belwooddoors_price-26.03.2023.csv', 'r');
        $result = fopen('belwooddoors_price-26.03.2023_update.csv', 'w');
        $total = 1;
        $errorCount = 0;
        $data = fgetcsv($priceList, 1000, "\t");
        fputcsv($result, $data, "\t");
        while (($data = fgetcsv($priceList, 1000, "\t")) !== FALSE) {
            $modelUrl = $data[8];
            $data[6] = $data[5];
            $data[5] = '';
            $this->error($total . ' - ' . $modelUrl);
            if ($modelUrl != $this->modelUrl) {
                $this->modelUrl = $modelUrl;
                $html = file_get_contents($modelUrl);
                $crawler = new Crawler($html);
                $this->offers = $this->getOffers($html);
                $this->prices = $this->getPriceList($crawler);
            }
            try {
                $price = $this->prices[$data[3]];
                /*                if ($price != $data[5]){
                                    $this->warn($price . " - " . $data[5]);
                                }*/
                $data[5] = ceil($price);
                $total++;
            } catch (\Exception $exception) {
                $errorCount++;
                $this->info($exception->getMessage());
            }
            fputcsv($result, $data, "\t");
        }
        $this->info('Всего:' . $total);
        $this->error('Ошибок:' . $errorCount);
    }

    private function getModelUrls(string $url)
    {
        $html = file_get_contents($url);
        $crawler = new Crawler($html);
        $modelNodes = $crawler->filter('div.catalog-item');
        foreach ($modelNodes as $modelNode) {
            $modelCrawler = new Crawler($modelNode);
            $modelUrl = self::URL . $modelCrawler->filter('a')->attr('href');
            $this->modelUrls[] = $modelUrl;
        }
    }

    private function getPageUrls(string $url)
    {
        $pageUrls = [$url];
        $html = file_get_contents($url);
        $crawler = new Crawler($html);
        $nodes = $crawler->filter('a.pagination__item');
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $url = self::URL . $crawler->attr('href');
            if (!in_array($url, $pageUrls)) {
                $pageUrls[] = $url;
            }
        }
        return $pageUrls;
    }

    private function getProduct(Crawler $crawler)
    {
        $product = new BelwoodDoorsProduct();
        $this->getCategories($crawler);
        $product->category = $this->category;
        $product->subCategory1 = $this->subCategory1;
        $product->subCategory2 = $this->subCategory2;
        $this->filterCanvasSizes = $this->getCanvasSizes($crawler);
        $this->filterColors = $this->getColors($crawler);
        $this->filterGlasses = $this->getGlasses($crawler);
        $this->modelImages = $this->getImages($crawler);
        $this->prices = $this->getPriceList($crawler);
        /*        dd($this->filterGlasses);
                dd($this->filterGlasses, $this->filterColors, $this->filterCanvasSizes, $this->modelImages, $this->offers, $this->prices);*/
        $product->parsingUrl = $this->modelUrl;
        $product->model = $this->getModel($crawler);
        $this->getProductVariants($product, $crawler);
    }

    private function getImages(Crawler $crawler)
    {
        $images = [];
        $nodes = $crawler->filter('div.product-door');
        foreach ($nodes as $node) {
            $imageCrawler = new Crawler($node);
            $id = $imageCrawler->attr('data-offer-id');
            $image = $imageCrawler->filter('div.product-preview__big-images > img')->attr('data-src');
            $images[$id] = self::URL . $image;
        }
        return $images;
    }


    private function getProductDescription(Crawler $crawler, $offer)
    {
        $description = '';
        $parameters = $crawler->filter('ul.product-info-parameters__list > li');
        if ($parameters->count() > 0) {
            $description = '<div><b>Характеристики:</b></div>';
            foreach ($parameters as $parameter) {
                $parameterCrawler = new Crawler($parameter);
                $parameterValue = $this->getParameter($parameterCrawler, $offer);
                if (!empty($parameterValue)) {
                    $description .= '<div><b>' . $parameterValue['title'] . ': </b>' . $parameterValue['value'] . '</div>';
                }
            }
        }
        $descriptionNode = $crawler->filter('div.product-info__text');
        if ($descriptionNode->count() > 0) {
            $description .= '<p><b>Описание:</b></p>';
            $descriptionValue = $descriptionNode->html();
            $descriptionValue = preg_replace('/\s?<iframe[^>]*?>.*?<\/iframe>\s?/si', '', $descriptionValue);
            $description .= $descriptionValue;
        }
        return $description;
    }

    private function getParameter(Crawler $crawler, string $offer)
    {
        if (!empty($crawler->attr('data-offer-id')) && $crawler->attr('data-offer-id') != $offer) {
            return false;
        }
        $title = $crawler->filter('div.product-info-parameters__key')->text();
        $value = $crawler->filter('div.product-info-parameters__value')->text();
        $value = preg_replace('/рис. [\d]+/', '', $value);

        return [
            'title' => $title,
            'value' => trim($value)
        ];
    }

    private function getProductVariants(BelwoodDoorsProduct $product, Crawler $crawler)
    {
        foreach ($this->filterCanvasSizes as $canvasSizeKey => $canvasSize) {
            $product->canvasSize = $canvasSize;
            foreach ($this->filterColors as $colorKey => $color) {
                $product->color = $color;
                if (!empty($this->filterGlasses)) {
                    foreach ($this->filterGlasses as $glassKey => $glass) {
                        $product->glass = $glass;
                        $offer = $this->getOffer($canvasSizeKey, $colorKey, $glassKey);
                    }
                } else {
                    $product->glass = '';
                    $offer = $this->getOffer($canvasSizeKey, $colorKey);
                }
                if (!empty($offer)) {
//                      dd($this->prices, $offer);
                    $product->price = ceil((float)$this->prices[$offer]);
                    $product->image = $this->modelImages[$offer];
                    $product->name = $this->getProductName($product);
                    $product->metaDescription = $product->metaKeywords = $product->metaTitle = $product->name;
                    $product->description = $this->getProductDescription($crawler, $offer);
                    $product->artikul = $offer;
                    $product->setInnerArticul();
                    $this->info($product->name . ' - ' . $product->parsingUrl);
                    $product->exportCsv($this->file);
                }
            }
        }
    }

    private function getOffer(string $canvasSize, string $color, string $glass = null)
    {
        foreach ($this->offers as $offerKey => $offer) {
            if (in_array($canvasSize, $offer) && in_array($color, $offer)) {
                if (empty($glass)) {
                    return $offerKey;
                }
                if (in_array($glass, $offer)) {
                    return $offerKey;
                }
            }
        }
        return false;
    }

    private function getArticul(Crawler $crawler)
    {
        $articul = $crawler->filter('span.item-articul')->text();
        $articul = trim(str_replace('Артикул:', '', $articul));
        return $articul;
    }

    private function getColor(Crawler $crawler)
    {
        $node = $crawler->filter('ul.color > li.active > img');
        if (count($node) > 0) {
            $color = $node->attr('title');
            $color = mb_strtolower(str_replace('ые', 'ый', $color));
            return $color;
        }
        return '';
    }

    private function getProductName(BelwoodDoorsProduct $product)
    {
        $name = 'Дверь ' . $product->model;
        if (!empty($product->color)) {
            $name .= ' / Цвет ' . $product->color;
        }
        if (!empty($product->glass)) {
            $name .= ' / ' . $product->glass;
        }
        $name .= ' / Двери Belwooddoors';

        return $name;
    }

    private function getModel(Crawler $crawler)
    {
        $name = $crawler->filter('h1')->text();
        $name = str_replace('Распашная', '', $name);
        $name = preg_replace('/^(.+?)\s\(.+$/', '\\1', $name);
        $name = trim($name);
        return $name;
    }

    private function getCanvasSizes(Crawler $crawler)
    {
        $canvasSizes = [];
        $nodes = $crawler->filter('div.filter-size-canvas > a');
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $canvasSize = $crawler->text();
            $canvasSize = str_replace(',', '.', $canvasSize);
            $arrCanvasSizes = explode("х", $canvasSize);
            $canvasSize = $arrCanvasSizes[0] * 100 . '*' . $arrCanvasSizes[1] * 100;
            $canvasSizes[$crawler->attr('data-id')] = $canvasSize;
        }
        return $canvasSizes;
    }

    private function getPriceList(Crawler $crawler)
    {
        $priceList = [];
        $nodes = $crawler->filter('div.detail-price-base > div');
        foreach ($nodes as $node) {
            $priceNode = new Crawler($node);
            if (!empty($priceNode->attr('data-offer-id'))) {
                $id = $priceNode->attr('data-offer-id');
                $price = $priceNode->filter('div.product-filter-price-tabs__discount')->attr('data-base-price');
                $priceList[$id] = ceil($price);
            }
        }
        return $priceList;
    }

    private function getOffers(string $html)
    {
        preg_match('/offers = (.*?), k;/si', $html, $results);
        $json = json_decode($results[1], true);
        $offers = Arr::first($json);

        return $offers;
    }

    private function getColors(Crawler $crawler)
    {
        $colors = [];
        $node = $crawler->filter('div.filter-main-color')->outerHtml();
        $crawler = new Crawler($node);
        $nodes = $crawler->filter('a');
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $color = $crawler->filter('img')->attr('alt');
            $colors[$crawler->attr('data-id')] = $color;
        }
        return $colors;
    }

    private function getGlasses(Crawler $crawler)
    {
        $glasses = [];
        $node = $crawler->filter('div.filter-glass-color');
        if ($node->count() == 0) {
            return $glasses;
        }
        $node = $node->outerHtml();
        $crawler = new Crawler($node);
        $nodes = $crawler->filter('a');
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $glass = $crawler->filter('img')->attr('alt');
            $glass = preg_replace('/рис. [\d]+/', '', $glass);
            $glasses[$crawler->attr('data-id')] = trim($glass);
        }
        return $glasses;
    }

    private function getCategories(Crawler $crawler)
    {
        $this->category = $crawler->filter('a.breadcrumbs__link')->eq(2)->text();
        $this->subCategory1 = $crawler->filter('a.breadcrumbs__link')->eq(3)->text();
    }
}

