<?php

namespace App\Console\Commands;

use App\Models\DverProduct;
use App\Models\LuxorProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\DomCrawler\Crawler;

class LuxorDveri extends Command
{
    const URL = 'https://luxor-dveri.ru';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsing:luxor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсинг https://luxor-dveri.ru/';

    protected $urls = [
        'https://luxor-dveri.ru/catalog/mezhkomnatnye-dveri/luxor-shpon/?SHOW-BY=3' => 'Двери в шпоне',
        'https://luxor-dveri.ru/catalog/mezhkomnatnye-dveri/ekoshpon/?SHOW-BY=3' => 'Двери в экошпоне',
        'https://luxor-dveri.ru/catalog/mezhkomnatnye-dveri/emal/?SHOW-BY=3' => 'Двери в эмали',
    ];
    protected $file;
    protected string $category = '';
    protected string $subCategory1 = '';
    protected string $subCategory2 = '';
    private array $modelUrls = [];
    private string $modelUrl;
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
        $this->file = fopen('luxor-dveri.csv', 'w');
        fputcsv($this->file, LuxorProduct::$headers, "\t");
        foreach ($this->urls as $url => $data) {
            $this->subCategory1 = $data;
            $this->getModelUrls($url);
            foreach ($this->modelUrls as $modelUrl) {
                $html = file_get_contents($modelUrl);
                $crawler = new Crawler($html);
                $this->modelUrl = $modelUrl;
                $this->getProduct($crawler);
            }
        }
    }

    private function getModelUrls(string $url)
    {
        $html = file_get_contents($url);
        $crawler = new Crawler($html);
        $modelNodes = $crawler->filter('li.catalog_item');
        foreach ($modelNodes as $modelNode) {
            $modelCrawler = new Crawler($modelNode);
            $modelUrl = $modelCrawler->filter('a')->attr('href');
            $this->modelUrls[] = self::URL.$modelUrl;
        }
    }


    private function getProduct(Crawler $crawler)
    {
        $product = new LuxorProduct();
        $product->subCategory1 = $this->subCategory1;
        $product->subCategory2 = $this->subCategory2;
        $canvasSizes = $this->getCanvasSizes($crawler);
        foreach ($canvasSizes as $id => $canvasSize) {
            $product->canvasSize = $canvasSize;
            $variantUrl = $this->modelUrl.'?size='.$id;
            $product->parsingUrl = $variantUrl;
            $html = file_get_contents($variantUrl);
            $crawler = new Crawler($html);
            $this->getProductVariants($product, $crawler);
        }
    }


    private function getProductDescription(Crawler $crawler)
    {
        $description = '';
        $complect = $this->getComplectDescription($crawler);
        if (!empty($complect)) {
            $description .= "<b>Комплектующие</b><br>";
            $description .= $complect;
        }
        $node = $crawler->filter('#opisanie_table');
        if (count($node) > 0) {
            $description .= "<p><b>Описание</b></p>";
            $crawler = new Crawler($node->html());
            $rows = $crawler->filter('tr');
            foreach ($rows as $row) {
                $rowCrawler = new Crawler($row);
                $header = trim($rowCrawler->filter('td')->eq(0)->text());
                if (strpos($header, 'Микроразметка') !== false) {
                    continue;
                }
                if (strpos($header, 'Артикул') !== false) {
                    continue;
                }
                $description .= '<div>';
                $description .= '<b>'.$header.'</b>';
                $valueNode = $rowCrawler->filter('td')->eq(1);
                if (count($valueNode) != 0) {
                    $description .= ' '.trim($valueNode->text());
                }
                $description .= '</div>';
            }
        }

        return $description;
    }

    private function getComplectDescription(Crawler $crawler)
    {
        $description = '';
        $node = $crawler->filter('#komplekt_table');
        if (count($node) > 0) {
            $node = $node->html();
            $complectCrawler = new Crawler($node);
            $cells = $complectCrawler->filter('td');
            if (count($cells) > 0) {
                foreach ($cells as $cell) {
                    $crawler = new Crawler($cell);
                    $html = $crawler->html();
                    $cellCrawler = new Crawler($html);
                    $span = $cellCrawler->filter('span');
                    if (count($span) > 0) {
                        for ($i = 0; $i < count($span); $i++) {
                            $html = str_replace($span->eq($i)->outerHtml(), '', $html);
                            $html = str_replace(' - <br>', '<br>', $html);
                        }
                    }
                    $images = $crawler->filter('a');
                    if (count($images) > 0) {
                        for ($i = 0; $i < count($images); $i++) {
                            $aHtml = $images->eq($i)->outerHtml();
                            $imageCrawler = new Crawler($aHtml);
                            $image = $imageCrawler->filter('img')->outerHtml();
                            $html = str_replace($aHtml, $image.'<br>', $html);
                        }
                    }
                    $description .= $html;
                    $description .= '<br>';
                }
            }
        }
        return $description;
    }

    private function getProductVariants(LuxorProduct $product, Crawler $crawler)
    {
        $product->description = $this->getProductDescription($crawler);
        $product->artikul = $this->getArticul($crawler);
        $product->color = $this->getColor($crawler);
        $product->glass = $this->getGlass($crawler);
        dd($product);
        $modelData = $this->getModel($crawler);
        $product->model = $modelData['model'];
        $product->color = $modelData['color'];
        $product->glass = $modelData['glass'];
        $product->name = $this->getProductName($product);
        $product->metaDescription = $product->metaKeywords = $product->metaTitle = $product->name;
        $product->price = $crawler->filter('#price_for_polotno_detail_page')->text();
        $product->image = self::URL.$crawler->filter('#main_image_big')->attr('src');
        $this->info($product->name.' - '.$product->artikul);
        $product->exportCsv($this->file);
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
            return $node->attr('title');
        }
        return '';
    }

    private function getGlass(Crawler $crawler)
    {
        $glassMaps = [
            'Глухие' => '',
            'С матовым стеклом' => 'Стекло матовое',
            'С черным стеклом' => 'Стекло черное',
            'Со светлым стеклом' => 'Стекло светлое',
            'Со стеклом' => 'Стекло'
        ];

        $name = $crawler->filter('span.name')->text();
        foreach ($glassMaps as $key => $glass) {
            if (strpos($name, $key) !== false) {
                return $glass;
            }
        }
        return '';
    }

    private function getProductName(DverProduct $product)
    {
        $name = 'Дверь '.$product->model;
        if (!empty($product->color)) {
            $name .= ' / Цвет '.$product->color;
        }
        if (!empty($product->glass)) {
            $name .= ' / '.$product->glass;
        }
        if ($product->manufacturer != 'Дверная Биржа') {
            $name .= ' / Двери '.$product->manufacturer;
        } else {
            $name .= ' / '.$product->manufacturer;
        }

        return $name;
    }

    private function getModel(Crawler $crawler)
    {
        $data = [
            'articul' => '',
            'model' => '',
            'color' => '',
            'glass' => ''
        ];
        $node = $crawler->filter('#opisanie_table')->html();
        $crawler = new Crawler($node);
        $rows = $crawler->filter('tr');
        foreach ($rows as $row) {
            $rowCrawler = new Crawler($row);
            $header = trim($rowCrawler->filter('td')->eq(0)->text());
            switch ($header) {
                case 'Модель:':
                    $data['model'] = $header = trim($rowCrawler->filter('td')->eq(1)->text());
                    break;
                case 'Цвет:':
                    $data['color'] = $header = trim($rowCrawler->filter('td')->eq(1)->text());
                    break;
                case 'Стекло:':
                    $data['glass'] = $header = trim($rowCrawler->filter('td')->eq(1)->text());
                    break;
                default:
                    break;
            }
        }

        return $data;
    }

    private function getCanvasSizes(Crawler $crawler)
    {
        $canvasSizes = [];
        $nodes = $crawler->filter('ul.size > li');
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $canvasSize = $crawler->text();
            $canvasSizes[$crawler->attr('id')] = $canvasSize;
        }
        return $canvasSizes;
    }

    private function getManufacturer(Crawler $crawler)
    {
        $node = $crawler->filter('h3 > a')->eq(2);
        if (count($node) > 0) {
            return $node->text();
        }
        return '';
    }
}

