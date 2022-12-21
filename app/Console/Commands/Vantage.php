<?php

namespace App\Console\Commands;

use App\Models\ProfilProduct;
use App\Models\VantageProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\DomCrawler\Crawler;

class Vantage extends Command
{
    const URL = 'https://vantage.su';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsing:vantage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсинг https://vantage.su/';

    protected $file;
    protected $urls = [
        0 => [
            'url' => 'https://vantage.su/dvernye-ruchki/',
            'category' => 'Дверные ручки ЦАМ',
            'name' => 'Дверная ручка'
        ],
        1 => [
            'url' => 'https://vantage.su/dver-ruchka-alumin/',
            'category' => 'Дверные алюминиевые',
        ],
        2 => [
            'url' => 'https://vantage.su/nakladki-pod-tsylindr/',
            'category' => 'Накладки под цилиндр',
        ],
        3 => [
            'url' => 'https://vantage.su/santehnicheskye-zavertki/',
            'category' => 'Накладки сантехнические',
        ],
        4 => [
            'url' => 'https://vantage.su/mezhkomnatnye-zamki/',
            'category' => 'Межкомнатные замки',
        ],
        5 => [
            'url' => 'https://vantage.su/dvernye-petli/',
            'category' => 'Дверные петли',
        ],
        6 => [
            'url' => 'https://vantage.su/tortsevye-shpingalety/',
            'category' => 'Торцевые шпингалеты',
        ],
        7 => [
            'url' => 'https://vantage.su/mechanizm-cilindr/',
            'category' => 'Механизмы цилиндрические',
        ],
        8 => [
            'url' => 'https://vantage.su/dver-ogranichitel/',
            'category' => 'Дверные ограничители',
        ],
        9 => [
            'url' => 'https://vantage.su/dovodchiki/',
            'category' => 'Доводчики',
        ],
        10 => [
            'url' => 'https://vantage.su/electro-zamki/',
            'category' => 'Электромеханичееские замки',
        ],
        11 => [
            'url' => 'https://vantage.su/razdvig-sistem/',
            'category' => 'Раздвижные системы',
        ],
        12 => [
            'url' => 'https://vantage.su/bronirovannye-nakladki/',
            'category' => 'Бронированные накладки',
        ],
        13 => [
            'url' => 'https://vantage.su/furnitura-dlya-finskih-dverei/',
            'category' => 'Фурнитура для финских дверей',
        ],
        14 => [
            'url' => 'https://vantage.su/furnitura-iz-nerzaveyushey-stali/',
            'category' => 'Фурнитура из нержавеющей стали',
        ],
        15 => [
            'url' => 'https://vantage.su/productia-agb/',
            'category' => 'Продукция AGB',
        ],
    ];
    protected $category;

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
        $filename = 'vantage.csv';
        $this->file = fopen($filename, 'w');
        fputcsv($this->file, VantageProduct::$headers, "\t");
        foreach ($this->urls as $category) {
            $this->category = $category;
            $html = file_get_contents($this->category['url']);
            $crawler = new Crawler($html);
            $productUrls = $this->getProductUrls($crawler);
            foreach ($productUrls as $productUrl) {
                try {
                    $this->getProduct($productUrl);
                } catch (\Exception $exception) {
                    $this->error($productUrl.$exception->getTraceAsString());
                }
            }
        }
    }

    private function getProductUrls(Crawler $crawler)
    {
        $urls = [];
        $nodes = $crawler->filter('div.tovarbox');
        foreach ($nodes as $node) {
            $modelNode = new Crawler($node);
            $urls[] = $modelNode->filter('a')->attr('href');
        }
        return $urls;
    }

    private function getProduct(string $url)
    {
        $html = file_get_contents($url);
        $crawler = new Crawler($html);
        $data = $this->getNodes($crawler);
        $product = new VantageProduct();
        $product->artikul = $data['name'];
        $product->name = $this->getProductName($data);
        $product->category = $this->category['category'];
        $product->metaDescription = $product->metaKeywords = $product->metaTitle = $product->name;
        $product->color = $data['color'];
        $product->description = $data['description'];
        $product->shortDescription = $data['description'];
        $product->parsingUrl = $url;
        $product->image = $this->getProductImage($crawler);
        $this->info($product->name);
        $product->exportCsv($this->file);
    }

    private function getProductImage(Crawler $crawler)
    {
        $image = $crawler->filter('a.highslide');
        if (count($image) > 0) {
            return $image->attr('href');
        }
        $image = $crawler->filter('img.xfieldimage');
        if (count($image) > 0) {
            return self::URL.$image->attr('src');
        }
        return '';
    }

    private function getProductName(array $data)
    {
        $productName = $data['category_name'];
        $productName .= ' '.$data['name'];
        if (!empty($data['color'])) {
            $productName .= ' '.$data['color'];
        }
        return $productName;
    }
    private function getNodes(Crawler $crawler): array
    {
        $mapCategoryName = [
            'Дверные ручки' => 'Дверная ручка',
            'Накладки под цилиндр' => 'Накладка под цилиндр',
            'Накладки сантехнические' => 'Накладка сантехническая',
        ];
        $result = [
            'category_name' => '',
            'name' => '',
            'color' => '',
            'description' => ''
        ];
        $node = $crawler->filter('div.tdcntnt')->html();
        $crawlerContent = new Crawler($node);
        $tableNode = $crawlerContent->filter('table')->last()->html();
        $dataCrawler = new Crawler($tableNode);
        $descriptionNode = $dataCrawler->filter('td')->eq(0);
        $descriptionText = $descriptionNode->text();
        $result['category_name'] = $mapCategoryName[$descriptionText] ?? $descriptionText;

        $descriptionNode = $dataCrawler->filter('td')->eq(1);
        $descriptionText = $descriptionNode->text();
        $result['name'] = trim(str_replace('Артикул:', '', $descriptionText));

        $descriptionNode = $dataCrawler->filter('td')->eq(2);
        $descriptionText = $descriptionNode->text();
        $result['color'] = trim(str_replace('Цвет:', '', $descriptionText));

        $descriptionNode = $dataCrawler->filter('td')->eq(3);
        $descriptionText = $descriptionNode->text();
        if (trim($descriptionText != 'Описание:')) {
            $result['description'] = $descriptionNode->html();
        }

        return $result;
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

