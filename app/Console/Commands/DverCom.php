<?php

namespace App\Console\Commands;

use App\Models\DverProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\DomCrawler\Crawler;

class DverCom extends Command
{
    const URL = 'https://dver.com';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsing:dver-com';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсинг https://dver.com';

    protected $urls = [
//        'https://dver.com/mezhkomnatnye-dveri/dveri-shponirovannye/',
        'https://dver.com/mezhkomnatnye-dveri/dveri-krashenye-emal/' => [
            'category' => 'Межкомнатные двери',
        ]
    ];
    protected $file;
    protected string $category = '';
    protected string $subCategory1 = '';
    protected string $subCategory2 = '';
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
        $this->file = fopen('dver.csv', 'w');
        fputcsv($this->file, DverProduct::$headers, "\t");
        foreach ($this->urls as $url => $data) {
            $this->category = $data['category'];
            $html = file_get_contents($url);
            $crawler = new Crawler($html);
            $this->subCategory1 = $crawler->filter('h1')->text();
            $pages = $this->getPages($crawler);
            if (empty($pages)) {
                $pages = [$url];
            }
            $this->getModelUrls($pages);
            foreach ($this->modelUrls as $modelUrl) {
                $html = file_get_contents($modelUrl);
                $crawler = new Crawler($html);
                $this->modelUrl = $modelUrl;
                $this->getProduct($crawler);
            }
        }
    }

    private function getModelUrls(array $urls)
    {
        foreach ($urls as $url) {
            $html = file_get_contents($url);
            $crawler = new Crawler($html);
            $modelNodes = $crawler->filter('div.transport__item');
            foreach ($modelNodes as $modelNode) {
                $modelCrawler = new Crawler($modelNode);
                $modelUrl = $modelCrawler->filter('a')->attr('href');
                $this->modelUrls[] = self::URL.$modelUrl;
            }
        }
    }


    private function getProduct(Crawler $crawler)
    {
        $product = new DverProduct();
        $product->category = $this->category;
        $product->subCategory1 = $this->subCategory1;
        $product->subCategory2 = $this->subCategory2;
        $articul = $crawler->filter('#first_articul')->text();
        $this->modelUrl = str_replace($articul, '', $this->modelUrl);
        $canvasSizes = $this->getCanvasSizes($crawler);
        $this->getProductVariants($product, $canvasSizes);
        exit;
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

    private function getProductVariants(DverProduct $product, array $canvasSizes)
    {

        foreach ($canvasSizes as $articul => $canvasSize) {
            $product->parsingUrl = $this->modelUrl.$articul;
            $this->info($product->parsingUrl);

            $html = file_get_contents($product->parsingUrl);
            $crawler = new Crawler($html);
            $product->description = $this->getProductDescription($crawler);
            $product->artikul = $articul;
            $modelData = $this->getModel($crawler);
            $product->model = $modelData['model'];
            $product->color = $modelData['color'];
            $product->glass = $modelData['glass'];
            $product->name = $this->getProductName($product);
            $product->metaDescription = $product->metaKeywords = $product->metaTitle = $product->name;
            $product->price = $crawler->filter('#price_for_polotno_detail_page')->text();
            $product->image = self::URL.$crawler->filter('#main_image_big')->attr('src');
            $product->canvasSize = $canvasSize;
            $this->info($product->name.' - '.$product->artikul);
            $product->exportCsv($this->file);
        }
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
        $name .= ' / '.$product->manufacturer;

        return $name;
    }

    private function getPages(Crawler $crawler)
    {
        $node = $crawler->filter('#pager_top');
        $crawler = new Crawler($node->outerHtml());
        $nodes = $crawler->filter('a');
        $pages = [];
        foreach ($nodes as $node) {
            $urlCrawler = new Crawler($node);
            $url = $urlCrawler->attr('href');
            $pages[] = self::URL.$url;
        }
        return $pages;
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
        $nodes = $crawler->filter('div.size_selecting_div');
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $canvasSizes[$crawler->attr('id')] = $crawler->text();
        }

        return $canvasSizes;
    }
}

