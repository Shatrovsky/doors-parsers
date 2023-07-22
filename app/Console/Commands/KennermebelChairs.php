<?php

namespace App\Console\Commands;

use App\Helpers\SlugHelper;
use App\Models\ProductChair;
use Illuminate\Console\Command;
use Symfony\Component\DomCrawler\Crawler;

class KennermebelChairs extends Command
{
    const URL = 'https://kennermebel.ru';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsing:kennermebel-chairs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсинг https://kennermebel.ru/';

    protected $urls = [
        'https://kennermebel.ru/collection/kuhonnye-stulya' => [
            'name' => 'Кухонные стулья'
        ],
        'https://kennermebel.ru/collection/metallicheskie' => [
            'name' => 'Металл'
        ],
        'https://kennermebel.ru/collection/derevyannye-2' => [
            'name' => 'Полукресла'
        ],
        'https://kennermebel.ru/collection/myagkie' => [
            'name' => 'Дизайнерские'
        ],
    ];
    protected $subCategory;

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
        $this->file = fopen('kennermebel_chairs.csv', 'w');
        fputcsv($this->file, ProductChair::$headers, "\t");
        foreach ($this->urls as $url => $data) {
            $this->subCategory = $data['name'];
            $page = 1;
            while (true) {
                $pageUrl = $url . "?page=" . $page;
                $html = file_get_contents($pageUrl);
                $crawler = new Crawler($html);
                $collectionNodes = $crawler->filter('form.product-preview');
                echo $pageUrl . " ";
                print_r(count($collectionNodes));
                echo "\n";
                if (count($collectionNodes) == 0) {
                    break;
                }
                foreach ($collectionNodes as $collectionNode) {
                    $crawler = new Crawler($collectionNode);
                    $productUrl = $crawler->filter('a')->attr('href');
                    $product = $this->getProduct($productUrl);
                    fputcsv($this->file, (array)$product, "\t");
                }
                $page++;
            }
        }
    }

    private function getProduct(string $url)
    {
        $this->info($url);
        $html = file_get_contents(self::URL . $url);
        $product = new ProductChair();
        /** @var Crawler $crawler */
        $crawler = new Crawler($html);
        $mainName = $crawler->filter('h1')->text();
        $this->setFilters($product, $crawler);
        $images = $this->getImages($crawler);
        $description = $this->getProductDescription($crawler);
        $product->shortDescription = $this->getShortDescription($crawler);
        $product->subCategory = $this->subCategory;
        $product->description = $description;
        $product->name = $mainName;
        $product->image = implode(" ", $images);
        $product->price = $this->getPrice($html);
        $product->parsingUrl = self::URL . $url;

        return $product;
    }

    private function getPrice(string $html): int
    {
        preg_match("/'products': \[([^<]+)\]/", $html, $matches);
        $data = json_decode($matches[1]);

        return (int)$data->price;
    }

    private function getProductDescription(Crawler $crawler): string
    {
        $description = '';
        $node = $crawler->filter('div.product-description');
        if (count($node) > 0) {
            $description .= $node->outerHtml();
        }
        $node = $crawler->filter('div.prod_descr-section');
        if (count($node) > 0) {
            $description .= $node->outerHtml();
        }

        return $description;
    }

    private function getImages(Crawler $crawler): array
    {
        $images = [];
        $nodes = $crawler->filter('div.product__slide-main');
        if (count($nodes) == 0) {
            return $images;
        }
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $imageNode = $crawler->filter('a');
            if (count($imageNode) > 0) {
                $images[] = $imageNode->attr('href');
            }
        }

        return $images;
    }

    private function setFilters(ProductChair $product, Crawler $crawler)
    {
        $node = $crawler->filter('div.properties-items');
        if (count($node) == 0) {
            return;
        }
        $crawler = new Crawler($node->html());
        $nodes = $crawler->filter('div');
        $titles = [
            'Общие|Цвет' => 'commonColor',
            'Каркас|Цвет' => 'skeletonColor',
            'Каркас|Материал' => 'skeletonMaterial',
            'Обивка|Цвет' => 'paddingColor',
            'Обивка|Тип Материала' => 'paddingMaterial',
        ];
        $title = '';
        $value = '';
        $itemGroup = '';
        foreach ($nodes as $item) {
            $nodeFilter = new Crawler($item);
            $class = trim($nodeFilter->attr('class'));
            if ($class == 'properties-items-title' || $class == 'properties-items-title hidden-item') {
                $itemGroup = $nodeFilter->text();
            }
            if ($class == 'property__name') {
                $itemSubgroup = $nodeFilter->text();
                $header = $itemGroup . '|' . $itemSubgroup;
                if (empty($title) || $title != $header) {
                    $title = $header;
                    $value = '';
                }
            }
            if ($class == 'property__content') {
                $value = $nodeFilter->text();
            }
            if (!empty($value)) {
                if (array_key_exists($title, $titles)) {
                    $property = $titles[$title];
                    $product->$property = $value;
                }
            }
        }
    }


    private function getShortDescription(Crawler $crawler): string
    {
        $text = '';
        $node = $crawler->filter('div.product__area-description');
        if (count($node) > 0) {
            $text .= $node->outerHtml();
        }

        $node = $crawler->filter('div.product__area-schema-img');
        if (count($node) > 0) {
            $text .= $node->outerHtml();
        }

        return $text;
    }

    private function getCollections($nodes): array
    {
        $collections = [];
        foreach ($nodes as $node) {
            $collectionNode = new Crawler($node);
            $link = $collectionNode->filter('a')->attr('href');
            $collections[] = self::URL . $link;
        }
        return $collections;
    }
}
