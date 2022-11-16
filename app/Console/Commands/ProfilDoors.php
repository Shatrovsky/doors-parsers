<?php

namespace App\Console\Commands;

use App\Models\ProfilProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\DomCrawler\Crawler;

class ProfilDoors extends Command
{
    const URL = 'https://profildoors.ru';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsing:profil-doors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсинг https://profildoors.ru/';

    protected $urls = [
        'https://profildoors.ru/catalog/serija_u/' => [
            'additional_image' => 'https://static.insales-cdn.com/r/3G--XWQa9xY/rs:fit:1000:1000:1/plain/images/products/1/7956/617422612/cd.png',
            'main_name' => 'Дверь ProfilDoors (Профиль Дорс) ',
            'short_description' => '<ul>
<li>Экологически безопасное влагостойкое матовое покрытие. Производство Renolit, Германия. Устойчивость к повреждениям и перепадам температуры.</li>
<li>Сборно-разборная конструкция. Полотно изготовлено из отдельных элементов (царг: филенка, стоевая, поперечина). Любой составной элемент заменяется при необходимости. В основе царг используется массив сосны и МДФ.</li>
<li>Максимальная высота полотен &ndash; 2300 мм, максимальная ширина &ndash; 1000 мм. Шаг нестандарта &ndash; 50 мм.</li>
<li><span style="font-size: 14pt;"><strong>Стоимость нестандартных дверей уточняйте у менеджера.</strong></span></li>
</ul>'
        ],
        /*        'https://kapelli-doors.ru/catalog/kapelli-multicolor/' => [
                    'additional_image' => 'https://static.insales-cdn.com/r/fPEg7su2qVY/rs:fit:1000:0:1/q:100/plain/images/products/1/5639/616388103/CLASSIC.png',
                    'main_name' => 'Дверь влагостойкая пластиковая ',
                    'file' => 'kapelli-multicolor.csv'
                ],
                'https://kapelli-doors.ru/catalog/kapelli-eco/' => [
                    'additional_image' => 'https://static.insales-cdn.com/r/WxvoH2f6DBE/rs:fit:1000:0:1/q:100/plain/images/products/1/5665/616388129/ECO.png',
                    'main_name' => 'Дверь влагостойкая пластиковая ',
                    'file' => 'kapelli-eco.csv'
                ],
                'https://kapelli-doors.ru/catalog/protivopozharnye/' => [
                    'additional_image' => 'https://static.insales-cdn.com/r/RugiQhWZgyQ/rs:fit:1000:0:1/q:100/plain/images/products/1/5859/616388323/PP.png',
                    'main_name' => '',
                    'file' => 'protivopozharnye.csv'
                ],*/
    ];
    protected $file;
    protected $additionalImage;
    protected $mainName;
    protected $shortDescription;
    protected $filterColors = [
//        "Аляска" => "alaska",
        "Антрацит" => "antracit",
        "Магнолия Сатинат" => "magnolia_satinat",
        "Черный Seidenmatt" => "black_mat",
        "Манхэттен" => "manhattan",
        "Шеллгрей" => "Shellgray",
        "ДаркВайт" => "Darkwhaite",
        "Санд" => "sand",
        "Грей" => "grey",
    ];
    protected $filterGlasses = [];
    protected $filterMoldings = [];
    protected $models = [];
    protected $products = [];


    protected $canvasSizes = [
        '200*60',
        '200*70',
        '200*80',
        '200*90',
//        'Нестандартный размер'
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
        foreach ($this->urls as $url => $data) {
            $html = file_get_contents($url);
            $this->additionalImage = $data['additional_image'];
            $this->mainName = $data['main_name'];
            $this->shortDescription = $data['short_description'];
            $crawler = new Crawler($html);
            $this->models = $this->getModels($crawler);
            $this->getFilters($crawler);
            foreach ($this->filterColors as $color) {
                $this->info($color);
                $filename = 'profil-series-u-'.$color.'.csv';
                $this->file = fopen($filename, 'w');
                fputcsv($this->file, ProfilProduct::$headers, "\t");
                foreach ($this->models as $model => $modelUrl) {
                    $variantUrls = $this->getVariantUrls($modelUrl, $color);
                    foreach ($variantUrls as $variantUrl) {
                        echo $variantUrl."\n";
                        $this->parseProduct($variantUrl);
                    }
                }
            }
        }
    }

    private function getImages(Crawler $crawler)
    {
        $images = self::URL.$crawler->filter('div.new-catalogue-detail-photo > a')->attr('href');
        if (!empty($this->additionalImage)) {
            $images .= ' '.$this->additionalImage;
        }
        return $images;
    }

    private function getColors($nodes): array
    {
        $colors = [];
        foreach ($nodes as $node) {
            $colorNode = new Crawler($node);
            $url = $colorNode->filter('a')->attr('data-xml-id');
            $name = $colorNode->text();
            $colors[$name] = $url;
        }
        return $colors;
    }

    private function getGlasses($nodes): array
    {
        $glasses = [];
        foreach ($nodes as $node) {
            $glassNode = new Crawler($node);
            $url = $glassNode->filter('a')->attr('data-xml-id');
            $name = $glassNode->text();
            $glasses[$name] = $url;
        }
        return $glasses;
    }

    private function getCollections($nodes): array
    {
        $collections = [];
        foreach ($nodes as $node) {
            $collectionNode = new Crawler($node);
            $link = $collectionNode->filter('a')->attr('href');
            $collections[] = self::URL.$link;
        }
        return $collections;
    }

    private function getProductName(ProfilProduct $product)
    {
        $name = $this->mainName.'Модель '.$product->model;
        if (!empty($product->color)) {
            $name .= ' / Цвет '.$product->color;
        }
        if (!empty($product->glass)) {
            $name .= ' / Стекло '.$product->glass;
        }
        return $name;
    }

    private function getProductDescription(Crawler $crawler)
    {
        $description = $crawler->filter('div.tab-content-padding > div')->outerHtml();
        $description = str_replace('дилеров', 'менеджеров', $description);
        return $description;
    }

    private function getFilters(Crawler $crawler)
    {
        $blocks = $crawler->filter('div.new-catalogue-filter-block');
        foreach ($blocks as $block) {
            $titleNode = new Crawler($block);
            $title = $titleNode->filter('div.new-catalogue-filter-title')->text();
            switch ($title) {
                case 'Цвет':
                    //$this->setFilterColors($block);
                    break;
                case 'Стекло':
                    $this->setFilterGlasses($block);
                    break;
                case 'Молдинги':
                    $this->setFilterMoldings($block);
                    break;
                default:
                    break;
            }
        }
    }

    private function setFilterColors($node)
    {
        $crawler = new Crawler($node);
        $colorNodes = $crawler->filter('div.catalog-filter-selector-item');
        foreach ($colorNodes as $colorNode) {
            $currentNode = new Crawler($colorNode);
            $value = $currentNode->filter('input')->attr('value');
            $name = $currentNode->text();
            $this->filterColors[$name] = $value;
        }
    }

    private function setFilterGlasses($node)
    {
        $crawler = new Crawler($node);
        $glassNodes = $crawler->filter('div.catalog-filter-selector-item');
        foreach ($glassNodes as $glassNode) {
            $currentNode = new Crawler($glassNode);
            $value = $currentNode->filter('input')->attr('value');
            $name = $currentNode->text();
            $this->filterGlasses[$name] = $value;
        }
    }

    private function setFilterMoldings($node)
    {
        $crawler = new Crawler($node);
        $moldingNodes = $crawler->filter('div.catalog-filter-selector-item');
        foreach ($moldingNodes as $moldingNode) {
            $currentNode = new Crawler($moldingNode);
            $value = $currentNode->filter('input')->attr('value');
            $name = $currentNode->text();
            $this->filterMoldings[$name] = $value;
        }
    }

    private function getVariantUrls(string $url, string $color)
    {
        $url = self::URL.$url.'?';
        $urls = [];
        foreach ($this->filterGlasses as $glass) {
            $urls[] = $url.'color='.$color.'&'.'glass='.$glass;
        }
        foreach ($this->filterMoldings as $molding) {
            $urls[] = $url.'color='.$color.'&'.'molding='.$molding;
        }
        return $urls;
    }

    private function parseProducts($url)
    {
        $productUrls = [];
        $html = file_get_contents($url);
        $crawler = new Crawler($html);
        $productNodes = $crawler->filter('div.new-catalogue-inner-list > div.new-catalogue-inner-item');
        foreach ($productNodes as $productNode) {
            $variantCrawler = new Crawler($productNode);
            $productUrls[] = $variantCrawler->filter('a')->attr('href');
        }
        return $productUrls;
    }

    private function parseProduct($url)
    {
        $parsingUrl = $url;
        $html = file_get_contents($parsingUrl);
        $crawler = new Crawler($html);
        $product = new ProfilProduct();
        $product->parsingUrl = $parsingUrl;
        $product->image = $this->getImages($crawler);
        $product->description = $this->getProductDescription($crawler);
        $product->shortDescription = $this->shortDescription;
        $product->model = $crawler->filter('h1')->text();
        $paramNodes = $crawler->filter('div.new-catalogue-detail-params-box');
        foreach ($paramNodes as $paramNode) {
            $node = new Crawler($paramNode);
            $title = $node->filter('div.new-catalogue-detail-params-title')->text();
            $value = $node->filter('div.new-catalogue-detail-params-value')->text();
            switch ($title) {
                case 'Цвет':
                    $product->color = $value;
                    break;
                case 'Стекло':
                    $product->glass = $value;
                    break;
                case 'Молдинг':
                    $product->molding = $value;
                    break;
                default:
                    break;
            }
        }
        $key = $product->model.$product->color.$product->glass.$product->molding;
        if (in_array($key, $this->products)) {
            return;
        }
        $this->products[] = $key;
        $product->name = $this->getProductName($product);
        $this->getCanvasVariants($product);
    }

    private function getCanvasVariants(ProfilProduct $product)
    {
        foreach ($this->canvasSizes as $canvasSize) {
            $product->canvasSize = $canvasSize;
            $product->exportCsv($this->file);
        }
    }

    private function getModels(Crawler $crawler)
    {
        $models = [];
        $nodes = $crawler->filter('div.new-catalogue-models-list > a');
        foreach ($nodes as $node) {
            $modelNode = new Crawler($node);
            $models[$modelNode->text()] = $modelNode->attr('href');
        }
        return $models;
    }
}
