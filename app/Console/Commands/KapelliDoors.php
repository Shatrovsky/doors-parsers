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

    protected $urls = [
        'https://kapelli-doors.ru/catalog/kapelli-classic/' => [
            'additional_image' => 'https://static.insales-cdn.com/r/C__OH_4o4QE/rs:fit:1000:0:1/q:100/plain/images/products/1/866/616366946/Kapelli-doors.png',
            'main_name' => 'Дверь влагостойкая пластиковая ',
            'file' => 'kapelli-classic.csv'
        ],
        'https://kapelli-doors.ru/catalog/kapelli-multicolor/' => [
            'additional_image' => 'https://static.insales-cdn.com/r/fPEg7su2qVY/rs:fit:1000:0:1/q:100/plain/images/products/1/5639/616388103/CLASSIC.png',
            'main_name' => 'Дверь влагостойкая пластиковая ',
            'file' => 'kapelli-multicolor.csv'
        ],
        'https://kapelli-doors.ru/catalog/kapelli-eco/' => [
            'additional_image' => 'https://static.insales-cdn.com/r/WxvoH2f6DBE/rs:fit:1000:0:1/q:100/plain/images/products/1/5665/616388129/ECO.png',
            'main_name' => 'Дверь влагостойкая пластиковая ',
            'file' => 'kapelli-eco.csv'
        ],
        'https://kapelli-doors.ru/catalog/protivopozharnye/' => [
            'additional_image' => 'https://static.insales-cdn.com/r/RugiQhWZgyQ/rs:fit:1000:0:1/q:100/plain/images/products/1/5859/616388323/PP.png',
            'main_name' => '',
            'file' => 'protivopozharnye.csv'
        ],
    ];
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
        foreach ($this->urls as $url => $data) {
            $this->file = fopen($data['file'], 'w');
            fputcsv($this->file, Product::$headers, "\t");
            $html = file_get_contents($url);
            $crawler = new Crawler($html);
            $collectionNodes = $crawler->filter('div.subcatalog__list > a');
            $descriptionNode = $crawler->filter('div.warning__text');
            if (count($descriptionNode) == 0) {
                $descriptionNode = $crawler->filter('div.construct.detail-content');
            }
            $description = $descriptionNode->outerHtml();
            $collectionUrls = $this->getCollections($collectionNodes);
            foreach ($collectionUrls as $collectionUrl) {
                $this->getProducts($url, $collectionUrl, $description);
            }
        }
    }

    private function getProducts(string $url, string $collectionUrl, string $mainDescription)
    {
        $html = file_get_contents($collectionUrl);
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
        $description = $mainDescription.'<br>';
        $title = $crawler->filter('div.construct__title');
        if (count($title) > 0) {
            $description .= $title->outerHtml();
        }
        $info = $crawler->filter('div.construct__info');
        if (count($info) > 0) {
            $description .= $info->outerHtml();
        }
        $product->shortDescription = $shortDescription;
        $product->description = $description;
        $product->parsingUrl = $url;
        foreach ($colors as $color => $image) {
            $product->name = $this->urls[$url]['main_name'].$mainName;
            if (count($colors) > 1) {
                $product->name .= ' '.$color;
            }
            $product->image = self::URL.$image;
            if (!empty($this->urls[$url]['additional_image'])) {
                $product->image .= ' '.$this->urls[$url]['additional_image'];
            }
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
