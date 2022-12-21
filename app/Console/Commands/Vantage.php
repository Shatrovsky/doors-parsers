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
        'Дверные ручки' => 'https://vantage.su/dvernye-ruchki/'
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
        foreach ($this->urls as $name => $url) {
            $html = file_get_contents($url);
            $crawler = new Crawler($html);
            $productUrls = $this->getProductUrls($crawler);
            $filename = $name.'.csv';
            $this->file = fopen($filename, 'w');
            fputcsv($this->file, VantageProduct::$headers, "\t");
            foreach ($productUrls as $productUrl) {
                $this->getProduct($productUrl);
            }
            exit;
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
        $product->name = $data['name'];
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
        $image = $crawler->filter('a.highslide')->attr('href');
        return $image;
    }

    private function getNodes(Crawler $crawler): array
    {
        $result = [
            'name' => '',
            'color' => '',
            'description' => ''
        ];
        $node = $crawler->filter('div.tdcntnt')->html();
        $crawlerContent = new Crawler($node);
        $tableNode = $crawlerContent->filter('table')->last()->html();
        $dataCrawler = new Crawler($tableNode);
        $dataNodes = $dataCrawler->filter('td');
        foreach ($dataNodes as $dataNode) {
            $descriptionNode = new Crawler($dataNode);
            $descriptionText = $descriptionNode->text();
            if (strpos($descriptionText, 'Артикул:') === 0) {
                $result['name'] = trim(str_replace('Артикул:', '', $descriptionText));
            }
            if (strpos($descriptionText, 'Цвет:') === 0) {
                $result['color'] = trim(str_replace('Цвет:', '', $descriptionText));
            }
            if (strpos($descriptionText, 'Описание:') === 0) {
                $result['description'] = $descriptionNode->html();
            }
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

