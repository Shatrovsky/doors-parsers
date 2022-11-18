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
        /*        'https://profildoors.ru/catalog/serija_u/' => [
                    'additional_image' => 'https://static.insales-cdn.com/r/3G--XWQa9xY/rs:fit:1000:1000:1/plain/images/products/1/7956/617422612/cd.png',
                    'main_name' => 'Дверь ProfilDoors (Профиль Дорс) ',
                    'short_description' => '<ul>
        <li>Экологически безопасное влагостойкое матовое покрытие. Производство Renolit, Германия. Устойчивость к повреждениям и перепадам температуры.</li>
        <li>Сборно-разборная конструкция. Полотно изготовлено из отдельных элементов (царг: филенка, стоевая, поперечина). Любой составной элемент заменяется при необходимости. В основе царг используется массив сосны и МДФ.</li>
        <li>Максимальная высота полотен &ndash; 2300 мм, максимальная ширина &ndash; 1000 мм. Шаг нестандарта &ndash; 50 мм.</li>
        <li><span style="font-size: 14pt;"><strong>Стоимость нестандартных дверей уточняйте у менеджера.</strong></span></li>
        </ul>'
                ],*/
        'https://profildoors.ru/catalog/seriya_zn/' => [
            'additional_image' => 'https://static.insales-cdn.com/images/products/1/7263/618396767/KSHT1.jpg',
            'main_name' => 'Дверь ProfilDoors (Профиль Дорс) ',
            'short_description' => '<ul>
<li>Экологически безопасное влагостойкое покрытие, с новейшей эксклюзивной структурой, идеально передающей срез натурального дерева. Производство Renolit, Германия. Устойчивость к повреждениям и перепадам температуры.</li>
<li>Каркасно-щитовая дверь состоит из каркаса (брус сосны по периметру), наполнения (мелкоячеистая сота для обычной двери и трубчатое ДСП для усиленной) и щита &ndash; листа МДФ с покрытием или грунтовкой.</li>
<li>Стандартная высота полотна не более 2100 мм. Возможно изготовление нестандарта по высоте с шагом 50 мм, но не выше 2600 мм при стандартном погонаже, с погонажем INVISIBLE - до 3000 мм., максимальная ширина &ndash; 1000 мм. Шаг нестандарта &ndash; 50 мм.</li>
<li><span style="font-size: 14pt;"><strong>Стоимость нестандартных дверей уточняйте у менеджера.</strong></span></li>
</ul>'
        ],
    ];
    protected $file;
    protected $additionalImage;
    protected $mainName;
    protected $shortDescription;
    protected $filterColors = [];
    protected $filterGlasses = [];
    protected $filterMoldings = [];
    protected $filterInserts = [];
    protected $filterEdges = [];
    protected $models = [];
    protected $products = [];
    protected $colorName;
    protected $colorKey;
    protected $count = 0;


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
//            dd($this->filterColors, $this->filterInserts, $this->filterGlasses, $this->filterEdges);
            foreach ($this->filterColors as $colorKey => $color) {
                $this->error($color);
                $this->colorKey = $colorKey;
                $this->colorName = $color;
                $filename = 'profil-series-zn-'.$this->colorKey.'.csv';
                $this->file = fopen($filename, 'w');
                fputcsv($this->file, ProfilProduct::$headers, "\t");
                foreach ($this->models as $model => $modelUrl) {
                    $variantUrls = $this->getVariantUrls($modelUrl, $colorKey);
                    foreach ($variantUrls as $variantUrl) {
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

    private function getProductName(ProfilProduct $product)
    {
        $name = $this->mainName.'Модель '.$product->model;
        if (!empty($product->color)) {
            $name .= ' / Цвет '.$product->color;
        }
        if (!empty($product->glass)) {
            $name .= ' / Стекло '.$product->glass;
        }
        if (!empty($product->insert)) {
            $name .= ' / Вставка '.$product->insert;
        }
        if (!empty($product->edge)) {
            $name .= ' / Кромка '.$product->edge;
        }
        return $name;
    }

    private function getProductDescription(Crawler $crawler)
    {
        $description = $crawler->filter('div.tab-content-padding')->outerHtml();
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
                    $this->setFilterColors($block);
                    break;
                case 'Стекло':
                    $this->setFilterGlasses($block);
                    break;
                case 'Вставка':
                    $this->setFilterInserts($block);
                    break;
                case 'Кромка':
                    $this->setFilterEdges($block);
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
            $key = $currentNode->filter('input')->attr('value');
            $name = $currentNode->text();
            $this->filterColors[$key] = $name;
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

    private function setFilterInserts($node)
    {
        $crawler = new Crawler($node);
        $filterNodes = $crawler->filter('div.catalog-filter-selector-item');
        foreach ($filterNodes as $filterNode) {
            $currentNode = new Crawler($filterNode);
            $value = $currentNode->filter('input')->attr('value');
            $name = $currentNode->text();
            $this->filterInserts[$name] = urlencode($value);
        }
    }

    private function setFilterEdges($node)
    {
        $crawler = new Crawler($node);
        $filterNodes = $crawler->filter('div.catalog-filter-selector-item');
        foreach ($filterNodes as $filterNode) {
            $currentNode = new Crawler($filterNode);
            $value = $currentNode->filter('input')->attr('value');
            $name = $currentNode->text();
            $this->filterEdges[$name] = $value;
        }
    }

    private function getVariantUrls(string $url, string $color)
    {
        $url = self::URL.$url.'?';
        $urls = [];
        foreach ($this->filterGlasses as $glass) {
            foreach ($this->filterEdges as $edge) {
                $urls[] = $url.'color='.$color.'&'.'glass='.$glass.'&'.'edge='.$edge;
            }
        }
        foreach ($this->filterInserts as $insert) {
            foreach ($this->filterEdges as $edge) {
                $urls[] = $url.'color='.$color.'&'.'glass_insert='.$insert.'&'.'edge='.$edge;
            }
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
        $this->info($parsingUrl);
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
                case 'Кромка':
                    $product->edge = $value;
                    break;
                case 'Вставка':
                    $product->insert = $value;
                    break;
                default:
                    break;
            }
        }
        if ($product->color != $this->colorName) {
            return;
        }
        $key = $product->model.$product->color.$product->glass.$product->insert.$product->edge;
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
            if ($modelNode->attr('class') == 'disabled long-name') {
                continue;
            }
            $models[$modelNode->text()] = $modelNode->attr('href');
        }
        return $models;
    }
}

