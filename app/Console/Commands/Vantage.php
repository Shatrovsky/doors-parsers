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
    protected $additionalImage;
    protected $mainName;
    protected $shortDescription;
    protected $urls = [
        'Дверные ручки' => 'https://vantage.su/dvernye-ruchki/'
    ];
    protected $filterColors = [
        /*        "alaska" => "Аляска",
                "antracit" => "Антрацит",
                "magnolia_satinat" => "Магнолия Сатинат",*/
        "black_mat" => "Черный Seidenmatt",
        "manhattan" => "Манхэттен",
        "Shellgray" => "Шеллгрей",
        "Darkwhaite" => "ДаркВайт",
        "sand" => "Санд",
        "grey" => "Грей"
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
            foreach ($productUrls as $productUrl) {
                $this->getProduct($productUrl);
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
        $product = new VantageProduct();
        $product->name = $this->getProductName($crawler);
        $product->parsingUrl = $url;
        $product->image = $this->getProductImage($crawler);
    }

    private function getProductImage(Crawler $crawler)
    {
        $image = $crawler->filter('a.highslide')->attr('href');
        return $image;
    }

    private function getProductName(Crawler $crawler)
    {
        $node = $crawler->filter('div.tdcntnt')->html();
        $crawlerContent = new Crawler($node);
        $tableNode = $crawlerContent->filter('table')->last()->html();
        $dataCrawler = new Crawler($tableNode);
        $dataNodes = $dataCrawler->filter('td');
        foreach ($dataNodes as $dataNode) {
            $descriptionNode = new Crawler($dataNode);
            $this->info($descriptionNode->html());
        }
        exit;
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

