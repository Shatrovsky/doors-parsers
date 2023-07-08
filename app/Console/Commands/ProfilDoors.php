<?php

namespace App\Console\Commands;

use App\Models\ProfilProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\DomCrawler\Crawler;

class ProfilDoors extends Command
{
    const URL = 'https://profildoors.ru';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsing:profil-doors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсинг https://profildoors.ru/';
    protected $urls = [
/*        'https://profildoors.ru/catalog/seriya_pa/' => [
            'category' => 'Коллекции с инновационным эмалевым покрытием',
            'subCategory' => 'Серия PA',
        ],
        'https://profildoors.ru/catalog/seriya_pd/' => [
            'category' => 'Коллекции с инновационным эмалевым покрытием',
            'subCategory' => 'Серия PD',
        ],
        'https://profildoors.ru/catalog/seriya_p/' => [
            'category' => 'Коллекции с инновационным эмалевым покрытием',
            'subCategory' => 'Серия P',
        ],
        'https://profildoors.ru/catalog/seriya_pm/' => [
            'category' => 'Коллекции с инновационным эмалевым покрытием',
            'subCategory' => 'Серия PM',
        ],
        'https://profildoors.ru/catalog/seriya_pw/' => [
            'category' => 'Коллекции с инновационным эмалевым покрытием',
            'subCategory' => 'Серия PW',
        ],
        'https://profildoors.ru/catalog/seriya_la/' => [
            'category' => 'Коллекции с глянцевым покрытием',
            'subCategory' => 'Серия LA',
        ],*/
        'https://profildoors.ru/catalog/series_l/' => [
            'category' => 'Коллекции с глянцевым покрытием',
            'subCategory' => 'Серия L',
        ],
        'https://profildoors.ru/catalog/seriya_le/' => [
            'category' => 'Коллекции с глянцевым покрытием',
            'subCategory' => 'Серия LE',
        ],
        'https://profildoors.ru/catalog/seriya_n/' => [
            'category' => 'Коллекции с древесным покрытием',
            'subCategory' => 'Серия N',
        ],
        'https://profildoors.ru/catalog/seriya_na/' => [
            'category' => 'Коллекции с древесным покрытием',
            'subCategory' => 'Серия NA',
        ]
    ];
    /*    protected $urls = [
                    'https://profildoors.ru/catalog/seriya_pd/' => [
                        'additional_image' => 'https://static.insales-cdn.com/r/3G--XWQa9xY/rs:fit:1000:1000:1/plain/images/products/1/7956/617422612/cd.png',
                        'main_name' => 'Дверь ProfilDoors (Профиль Дорс) ',
                    ],
            'https://profildoors.ru/catalog/seriya_pw/' => [
                'additional_image' => 'https://static.insales-cdn.com/images/products/1/7263/618396767/KSHT1.jpg',
                'main_name' => 'Дверь ProfilDoors (Профиль Дорс) ',
                'short_description' => '<ul>
    <li>Экологически безопасное влагостойкое покрытие, с новейшей эксклюзивной структурой, идеально передающей срез натурального дерева. Производство Renolit, Германия. Устойчивость к повреждениям и перепадам температуры.</li>
    <li>Каркасно-щитовая дверь состоит из каркаса (брус сосны по периметру), наполнения (мелкоячеистая сота для обычной двери и трубчатое ДСП для усиленной) и щита &ndash; листа МДФ с покрытием или грунтовкой.</li>
    <li>Стандартная высота полотна не более 2100 мм. Возможно изготовление нестандарта по высоте с шагом 50 мм, но не выше 2600 мм при стандартном погонаже, с погонажем INVISIBLE - до 3000 мм., максимальная ширина &ndash; 1000 мм. Шаг нестандарта &ndash; 50 мм.</li>
    <li><span style="font-size: 14pt;"><strong>Стоимость нестандартных дверей уточняйте у менеджера.</strong></span></li>
    </ul>'
            ],
        ];*/
    protected $file;
    protected $additionalImage;
    protected $mainName = 'Дверь ProfilDoors (Профиль Дорс) ';
    protected $shortDescription;
    protected $filterColors = [];
    protected $filterGlasses = [];
    protected $filterMoldings = [];
    protected $filterInserts = [];
    protected $filterEdges = [];
    protected $filterProfiles = [];
    protected $models = [];
    protected $products = [];
    protected $colorName;
    protected $colorKey;
    protected $count = 0;
    protected $category = '';
    protected $subCategory1 = '';


    protected $canvasSizes = [
        '200*60',
        '200*70',
        '200*80',
        '200*90',
//        'Нестандартный размер'
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
        $filename = 'profilDoorsL2.csv';
        $this->file = fopen($filename, 'w');
        fputcsv($this->file, ProfilProduct::$headers, "\t");
        foreach ($this->urls as $url => $main) {
            $this->filterColors = [];
            $this->filterGlasses = [];
            $this->filterEdges = [];
            $this->filterProfiles = [];
            $this->filterInserts = [];
            $this->filterMoldings = [];
            $this->category = $main['category'];
            $this->subCategory1 = $main['subCategory'];
            $this->error($url);
            $html = file_get_contents($url);
            /*            $this->additionalImage = $data['additional_image'];
                        $this->mainName = $data['main_name'];*/
            $crawler = new Crawler($html);
            $this->shortDescription = $this->getShortDescription($crawler);
//            $this->shortDescription = $data['short_description'];
            $this->models = $this->getModels($crawler);
            $this->getFilters($crawler);
//            dd($this->filterColors, $this->filterInserts, $this->filterGlasses, $this->filterEdges, $this->filterProfiles);
            foreach ($this->models as $modelUrl) {
                $variantUrls = $this->getVariantUrls($modelUrl);
                if (empty($variantUrls)) {
                    $variantUrls[] = self::URL . $modelUrl;
                }

                foreach ($variantUrls as $variantUrl) {
                    try {
                        $this->parseProduct($variantUrl);
                    } catch (\Exception $exception) {
                        $this->warn("Ошибка загрузки {$variantUrl} " . $exception->getMessage());
                    }
                }
            }
        }
    }

    private function getShortDescription(Crawler $crawler)
    {
        $description = '';
        $node = $crawler->filter('div.series-header-text');
        if (count($node) > 0) {
            $description = $node->text();
        }
        return $description;
    }

    private function getImages(Crawler $crawler)
    {
        $images = '';
        $node = $crawler->filter('div.new-catalogue-detail-photo > a');
        if (count($node) > 0) {
            $images = self::URL . $node->attr('href');
        }
        if (!empty($this->additionalImage)) {
            $images .= ' ' . $this->additionalImage;
        }
        return $images;
    }

    private function getProductName(ProfilProduct $product)
    {
        $name = $this->mainName . 'Модель ' . $product->model;
        if (!empty($product->color)) {
            $name .= ' / Цвет ' . $product->color;
        }
        if (!empty($product->glass)) {
            $name .= ' / Стекло ' . $product->glass;
        }
        if (!empty($product->insert)) {
            $name .= ' / Вставка ' . $product->insert;
        }
        if (!empty($product->profile)) {
            $name .= ' / Профиль ' . $product->profile;
        }
        if (!empty($product->edge)) {
            $name .= ' / Кромка ' . $product->edge;
        }
        return $name;
    }

    private function getProductDescription(Crawler $crawler)
    {
        $description = '';
        $node = $crawler->filter('div.tab-content-padding');
        if (count($node) > 0) {
            $description = $node->outerHtml();
            $description = str_replace('дилеров', 'менеджеров', $description);
            $description = str_replace('src="/', 'src="' . self::URL . '/', $description);
            $description = str_replace("\t", ' ', $description);
        }

        return $description;
    }

    private function getFilters(Crawler $crawler)
    {
        $blocks = $crawler->filter('div.new-catalogue-filter-block');
        foreach ($blocks as $block) {
            $titleNode = new Crawler($block);
            $title = $titleNode->filter('div.new-catalogue-filter-title')->text();
            switch ($title) {
                case 'Цвет':
                    $this->setFilterColors($block);
                    break;
                case 'Стекло':
                    $this->setFilterGlasses($block);
                    break;
                case 'Вставка':
                    $this->setFilterInserts($block);
                    break;
                case 'Кромка':
                    $this->setFilterEdges($block);
                    break;
                case 'Профиль':
                    $this->setFilterProfiles($block);
                    break;
                default:
                    break;
            }
        }
    }

    private function setFilterColors($node)
    {
        $crawler = new Crawler($node);
        $colorNodes = $crawler->filter('div.catalog-filter-selector-item');
        foreach ($colorNodes as $colorNode) {
            $currentNode = new Crawler($colorNode);
            $key = $currentNode->filter('input')->attr('value');
            $name = $currentNode->text();
            $this->filterColors[$key] = $name;
        }
    }

    private function setFilterGlasses($node)
    {
        $crawler = new Crawler($node);
        $glassNodes = $crawler->filter('div.catalog-filter-selector-item');
        foreach ($glassNodes as $glassNode) {
            $currentNode = new Crawler($glassNode);
            $value = $currentNode->filter('input')->attr('value');
            $name = $currentNode->text();
            $this->filterGlasses[$name] = $value;
        }
    }

    private function setFilterInserts($node)
    {
        $crawler = new Crawler($node);
        $filterNodes = $crawler->filter('div.catalog-filter-selector-item');
        foreach ($filterNodes as $filterNode) {
            $currentNode = new Crawler($filterNode);
            $value = $currentNode->filter('input')->attr('value');
            $name = $currentNode->text();
            $this->filterInserts[$name] = urlencode($value);
        }
    }

    private function setFilterEdges($node)
    {
        $crawler = new Crawler($node);
        $filterNodes = $crawler->filter('div.catalog-filter-selector-item');
        foreach ($filterNodes as $filterNode) {
            $currentNode = new Crawler($filterNode);
            $value = $currentNode->filter('input')->attr('value');
            $name = $currentNode->text();
            $this->filterEdges[$name] = $value;
        }
    }

    private function setFilterProfiles($node)
    {
        $crawler = new Crawler($node);
        $filterNodes = $crawler->filter('div.catalog-filter-selector-item');
        foreach ($filterNodes as $filterNode) {
            $currentNode = new Crawler($filterNode);
            $value = $currentNode->filter('input')->attr('value');
            $name = $currentNode->text();
            $this->filterProfiles[$name] = $value;
        }
    }

    private function getVariantUrls(string $url)
    {
        $url = self::URL . $url;
        $urls = [];
        foreach ($this->filterColors as $colorKey => $color) {
            if (empty($this->filterInserts) && empty($this->filterGlasses) && empty($this->filterProfiles)) {
                $urlColor = $url . '?color=' . $colorKey;
                $urls[] = $urlColor;
                continue;
            }
            if (empty($this->filterInserts) && empty($this->filterGlasses)) {
                foreach ($this->filterProfiles as $profile) {
                    $urls[] = $url . '?color=' . $colorKey . '&' . 'profile_color=' . $profile;
                }
                continue;
            }
            foreach ($this->filterGlasses as $glass) {
                if (empty($this->filterEdges) && empty($this->filterProfiles)) {
                    $urls[] = $url . '?color=' . $colorKey . '&' . 'glass=' . $glass;
                    continue;
                }
                foreach ($this->filterEdges as $edge) {
                    $urls[] = $url . '?color=' . $colorKey . '&' . 'glass=' . $glass . '&' . 'edge=' . $edge;
                }
                foreach ($this->filterProfiles as $profile) {
                    $urls[] = $url . '?color=' . $colorKey . '&' . 'glass=' . $glass . '&' . 'profile_color=' . $profile;
                }
            }
            foreach ($this->filterInserts as $insert) {
                foreach ($this->filterEdges as $edge) {
                    $urls[] = $url . '?color=' . $colorKey . '&' . 'glass_insert=' . $insert . '&' . 'edge=' . $edge;
                }
                foreach ($this->filterProfiles as $profile) {
                    $urls[] = $url . '?color=' . $colorKey . '&' . 'glass_insert=' . $insert . '&' . 'profile_color=' . $profile;
                }
            }
        }

        return $urls;
    }

    private function parseProducts($url)
    {
        $productUrls = [];
        $html = file_get_contents($url);
        $crawler = new Crawler($html);
        $productNodes = $crawler->filter('div.new-catalogue-inner-list > div.new-catalogue-inner-item');
        foreach ($productNodes as $productNode) {
            $variantCrawler = new Crawler($productNode);
            $productUrls[] = $variantCrawler->filter('a')->attr('href');
        }
        return $productUrls;
    }

    private function parseProduct($url)
    {
        $parsingUrl = str_replace(" ", "%20", $url);
        $parsingUrl .= '&glass_color=Not+selected';
        $this->info($parsingUrl);
        $html = @file_get_contents($parsingUrl);
        $crawler = new Crawler($html);
        $product = new ProfilProduct();
        $product->category = $this->category;
        $product->subCategory1 = $this->subCategory1;
        $product->parsingUrl = $parsingUrl;
        $product->image = $this->getImages($crawler);
        $product->description = $this->getProductDescription($crawler);
        $product->shortDescription = $this->shortDescription;
        $product->model = $crawler->filter('h1')->text();
        $paramNodes = $crawler->filter('div.new-catalogue-detail-params-box');
        foreach ($paramNodes as $paramNode) {
            $node = new Crawler($paramNode);
            $title = $node->filter('div.new-catalogue-detail-params-title')->text();
            $value = $node->filter('div.new-catalogue-detail-params-value')->text();
//            echo  $title . " - " . $value . "\n";
            switch ($title) {
                case 'Цвет':
                    $product->color = $value;
                    break;
                case 'Стекло':
                    $product->glass = $value;
                    break;
                case 'Молдинг':
                    $product->molding = $value;
                    break;
                case 'Кромка':
                    $product->edge = $value;
                    break;
                case 'Вставка':
                    $product->insert = $value;
                    break;
                case 'Цвет профиля':
                    $product->profile = $value;
                    break;
                default:
                    break;
            }
        }
        $key = $product->model . $product->color . $product->glass . $product->insert . $product->edge . $product->profile;
        if (in_array($key, $this->products)) {
            return;
        }
        $this->products[] = $key;
        $product->name = $this->getProductName($product);
        $this->getCanvasVariants($product);
    }

    private function getCanvasVariants(ProfilProduct $product)
    {
        foreach ($this->canvasSizes as $canvasSize) {
            $product->canvasSize = $canvasSize;
            $product->exportCsv($this->file);
        }
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

