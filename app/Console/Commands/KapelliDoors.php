<?php

namespace App\Console\Commands;

use App\Helpers\SlugHelper;
use App\Models\Product;
use Illuminate\Console\Command;
use Symfony\Component\DomCrawler\Crawler;

class KapelliDoors extends Command
{
    const URL = 'https://kapelli-doors.ru';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsing:kapelli-doors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсинг https://kapelli-doors.ru/';
    protected $file;
    protected $headers = [
        'Базовый комплект:', 'Размер полотен:', 'Рекомендуемая фурнитура:', 'Комплектующие (приобретаются отдельно):'
    ];
    protected $canvasSizes = [
        '200*60',
        '200*70',
        '200*80',
        '200*90',
        'Нестандартный размер'
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
        $this->file = fopen('out.csv', 'w');
        fputcsv($this->file, Product::$headers, "\t");
        $html = file_get_contents('https://kapelli-doors.ru/catalog/kapelli-classic/');
        $crawler = new Crawler($html);
        $collectionNodes = $crawler->filter('div.subcatalog__list > a');
        $collectionUrls = $this->getCollections($collectionNodes);
        foreach ($collectionUrls as $collectionUrl) {
            $this->getProducts($collectionUrl);
        }
    }

    private function getProducts(string $url)
    {
        $html = file_get_contents($url);
        $product = new Product();
        /** @var Crawler $crawler */
        $crawler = new Crawler($html);
        $mainName = $crawler->filter('h1')->text();
        $descriptionNodes = $crawler->filter('div.card__info > div');
        $canvasSizes = $this->getCanvasSizes($descriptionNodes);
        $colorNodes = $crawler->filter('.card__subslider-item');
        if (count($colorNodes) == 0) {
            $colorNodes = $crawler->filter('.card__slider-item');
        }
        $colors = $this->getColors($colorNodes);
        $shortDescription = $this->getShortDescription($descriptionNodes);
        $description = $crawler->filter('div.construct__title')->outerHtml();
        $description .= $crawler->filter('div.construct__info')->outerHtml();
        $product->shortDescription = $shortDescription;
        $product->description = $description;
        $product->parsingUrl = $url;
        foreach ($colors as $color => $image) {
            $product->name = $mainName;
            if (count($colors) > 1) {
                $product->name .= ' '.$color;
            }
            $product->image = self::URL.$image;
            $this->makeProductVariants($product, $canvasSizes);
        }
    }

    private function makeProductColors(Product $product, $colors)
    {

    }

    private function makeProductVariants(Product $product, array $canvasSizes)
    {
        foreach ($this->canvasSizes as $canvasSize) {
            $product->canvasSize = $canvasSize;
            $product->exportCsv($this->file);
        }
    }

    private function getCanvasSizes($nodes): array
    {
        $currentCategory = null;
        $category = null;
        $canvasSizes = [];
        foreach ($nodes as $node) {
            $canvasSizesNode = new Crawler($node);
            $class = $canvasSizesNode->attr('class');
            if ($class == 'card__subtitle') {
                $category = $canvasSizesNode->text();
            }
            if ($currentCategory === null && $category != 'Размер полотен:') {
                continue;
            }
            if ($currentCategory == null && $category == 'Размер полотен:') {
                $currentCategory = $category;
                continue;
            }
            if ($category != null && $category != 'Размер полотен:') {
                break;
            }
            $canvasSizes[] = $canvasSizesNode->text();
        }
        return $canvasSizes;
    }

    private function getColors($nodes): array
    {
        $colors = [];
        foreach ($nodes as $node) {
            $colorNode = new Crawler($node);
            $image = $colorNode->filter('img')->attr('src');
            $name = $colorNode->text();
            $colors[$name] = $image;
        }
        return $colors;
    }

    private function getShortDescription($nodes): string
    {
        $currentCategory = null;
        $category = null;
        $text = '';
        foreach ($nodes as $node) {
            $shortDescriptionNode = new Crawler($node);
            $class = $shortDescriptionNode->attr('class');
            if ($class == 'card__subtitle') {
                $category = $shortDescriptionNode->text();
            }
            if ($currentCategory === null && $category != 'Базовый комплект:') {
                continue;
            }
            if ($currentCategory == null && $category == 'Базовый комплект:') {
                $currentCategory = $category;
            }
            if ($category != null && $category != 'Базовый комплект:') {
                break;
            }
            $text .= $shortDescriptionNode->outerHtml();
        }
        return $text;
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
}
