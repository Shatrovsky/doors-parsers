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
        'https://luxor-dveri.ru/catalog/mezhkomnatnye-dveri/luxor-shpon/?SHOW-BY=3' => 'Межкомнатные двери luxor (шпон)',
        'https://luxor-dveri.ru/catalog/mezhkomnatnye-dveri/ekoshpon/?SHOW-BY=3' => 'Межкомнатные двери экошпон',
        'https://luxor-dveri.ru/catalog/mezhkomnatnye-dveri/emal/?SHOW-BY=3' => 'Межкомнатные двери эмаль',
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
        $this->file = fopen('luxor-dveri3.csv', 'w');
        fputcsv($this->file, LuxorProduct::$headers, "\t");
        foreach ($this->urls as $url => $data) {
            $this->category = $data;
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
        $this->modelUrls = [];
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
        $product->category = $this->category;
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
        $description = $crawler->filter('div.tabs__content ')->eq(0)->html();
        return $description;
    }

    private function getProductVariants(LuxorProduct $product, Crawler $crawler)
    {
        $fullname = $crawler->filter('span.name')->text();
        $product->model = $this->getModel($crawler);
        $product->description = $this->getProductDescription($crawler);
        $product->artikul = $this->getArticul($crawler);
        $product->color = $this->getColor($crawler);
        $product->glass = $this->getGlass($fullname);
        $product->price = (int) $crawler->filter('#itog-price')->text();
        $product->name = $this->getProductName($product);
        $product->metaDescription = $product->metaKeywords = $product->metaTitle = $product->name;
        $product->image = self::URL.$crawler->filter('div.photo > img')->attr('data-webp-data-src');
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
            $color = $node->attr('title');
            $color = mb_strtolower(str_replace('ые', 'ый', $color));
            return $color;
        }
        return '';
    }

    private function getGlass(string $name)
    {
        $glassMaps = [
            'Глухие' => '',
            'С матовым стеклом' => 'Стекло матовое',
            'С черным стеклом' => 'Стекло черное',
            'Со светлым стеклом' => 'Стекло светлое',
            'Со стеклом' => 'Стекло'
        ];

        foreach ($glassMaps as $key => $glass) {
            if (strpos($name, $key) !== false) {
                return $glass;
            }
        }
        return '';
    }

    private function getProductName(LuxorProduct $product)
    {
        $name = 'Дверь '.$product->model;
        if (!empty($product->color)) {
            $name .= ' / Цвет '.$product->color;
        }
        if (!empty($product->glass)) {
            $name .= ' / '.$product->glass;
        }
        $name .= ' / Двери Люксор (LUXOR)';

        return $name;
    }

    private function getModel(Crawler $crawler)
    {
        $fullname = $crawler->filter('h1')->text();
        $name = preg_replace('/^(.+?)\s\(.+$/', '\\1', $fullname);
        $name = preg_replace('/^(.+?\d)\s.+$/', '\\1', $name);
        $name = str_replace('Межкомнатные двери', '', $name);
        $name = str_replace('Модель', '', $name);
        $name = trim($name);
        return $name;
    }

    private function getCanvasSizes(Crawler $crawler)
    {
        $canvasSizes = [];
        $nodes = $crawler->filter('ul.size > li');
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $canvasSize = $crawler->text();
            $arrCanvasSizes = explode("х", $canvasSize);
            $canvasSize = $arrCanvasSizes[1] / 10 .'*'.$arrCanvasSizes[0] / 10;
            $canvasSizes[$crawler->attr('id')] = $canvasSize;
        }
        return $canvasSizes;
    }
}

