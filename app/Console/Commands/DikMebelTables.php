<?php

namespace App\Console\Commands;

use App\Helpers\SlugHelper;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\DomCrawler\Crawler;

class DikMebelTables extends Command
{
    const URL = 'https://dik-mebel.ru';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsing:dik-mebel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсинг https://dik-mebel.ru';

    protected $urls = [
        'https://dik-mebel.ru/catalog/stoly-sovremennye/' => [
            'name' => 'Столы современные'
        ],
        'https://dik-mebel.ru/catalog/stoly-klassicheskie/' => [
            'name' => 'Столы классические'
        ],
    ];
    protected string $subCategory;
    protected array $productUrls = [];

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
        $this->file = fopen('dik-mebel_tables.csv', 'w');
        fputcsv($this->file, Product::$headers, "\t");
        $page = 1;
        foreach ($this->urls as $url => $data) {
            while (true) {
                $this->info($page);
                $productUrls = $this->getProductUrls($url . '?PAGEN_1=' . $page);
                if ($productUrls == false) {
                    exit;
                }
                foreach ($productUrls as $productUrl) {
                    $product = $this->getProduct($productUrl);
                    $product->exportCsv($this->file);

                }
                $page++;
            }
        }
        exit;
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
                    fputcsv($this->file, (array) $product, "\t");
                }
                $page++;
            }
        }
    }

    private function getProductUrls(string $url): array|bool
    {
        $urls = [];
        $html = file_get_contents($url);
        $crawler = new Crawler($html);
        $html = $crawler->filter('div.catalog-list')->outerHtml();
        $crawler = new Crawler($html);
        $nodes = $crawler->filter('a.d-block');
        foreach ($nodes as $node) {
            $crawlerProduct = new Crawler($node);
            $url = $crawlerProduct->attr('href');
            if (in_array($url, $this->productUrls)) {
                return false;
            }
            $this->productUrls[] = $url;
            $this->warn($url);
            $urls[] = $url;
        }

        return $urls;
    }

    private function getProduct(string $url)
    {
        $this->info($url);
        $html = file_get_contents(self::URL . $url);
        $product = new Product();
        $crawler = new Crawler($html);
        $product = $this->getFilters($product, $crawler);
        $mainName = $crawler->filter('h1')->text();
        $categories = $this->getCategories($crawler);
        $images = $this->getImages($crawler);
        $product->description = $this->getProductDescription($crawler);
        $product->subCategory = $categories[2];
        $product->name = $mainName;
        $product->image = implode(" ", $images);
        $product->price = $this->getPrice($crawler);
        $product->parsingUrl = self::URL . $url;

        return $product;
    }

    private function getPrice(Crawler $crawler): int
    {
        $node = $crawler->filter('div.actual-price')->text();
        $stringPrice = str_replace(' ', '', $node);

        return (int) $stringPrice;
    }

    private function getCategories(Crawler $crawler): array
    {
        $node = $crawler->filter('div.inner-breadcrumb-wrap')->html();
        $crawlerCategories = new Crawler($node);
        $nodes = $crawlerCategories->filter('li');
        $categories = [];
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $categories[] = $crawler->text();
        }
        return $categories;
    }

    private function getProductDescription(Crawler $crawler): string
    {
        $description = '';
        $nodes = $crawler->filter('div.cart-block');
        if (count($nodes) == 0) {
            return $description;
        }
        $description .= $nodes->eq(1)->html();
        $description .= $nodes->eq(2)->html();
        $description .= $nodes->eq(3)->html();

        return $description;
    }

    private function getImages(Crawler $crawler): array
    {
        $images = [];
        $nodes = $crawler->filter('div.big-picture');
        if (count($nodes) == 0) {
            return $images;
        }
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $imageNode = $crawler->filter('img');
            if (count($imageNode) > 0) {
                $images[] = self::URL . $imageNode->attr('data-src');
            }
        }

        return $images;
    }

    private function getFilters(Product $product, Crawler $crawler)
    {
        $headers = [
            "Цвет опор" => 'supportColor',
            "Материал столешницы" => 'tableMaterial',
            "Материал опор" => 'supportMaterial',
            "Материал каркаса" => 'carcasMaterial',
            "Ширина столешницы (см)" => 'depth',
            "Высота (см)" => 'altitude',
            "Длина столешницы (см)" => 'width',
            "Гарантийный срок" => 'warranty',
            "Максимальная нагрузка (кг)" => 'maxWeight',
            "Форма столешницы" => 'tableForm',
            "Механизм раскладки" => 'mechanismType'
        ];
        $nodes = $crawler->filter('table.cart-char-table');
        if (count($nodes) == 0) {
            return $product;
        }
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $items = $crawler->filter('td');
            $header = $items->eq(0)->text();
            if (Arr::has($headers, $header)) {
                $property = $headers[$header];
                $product->$property = $items->eq(2)->text();
            }
        }
        return $product;
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
