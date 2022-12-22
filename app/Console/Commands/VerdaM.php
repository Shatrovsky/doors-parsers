<?php

namespace App\Console\Commands;

use App\Models\ProfilProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\DomCrawler\Crawler;
use function Symfony\Component\DomCrawler\text;

class VerdaM extends Command
{
    const URL = 'https://verda-m.ru';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsing:verda';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсинг https://verda-m.ru';

    protected $urls = [
        'https://verda-m.ru/catalog/dveri-loyard/'
    ];
    protected $file;
    protected string $category;
    protected string $subCategory1;
    protected string $subCategory2;
    protected array $filterTypes = [];
    protected array $filterCanvasSizes = [];
    protected array $filterColors = [];
    private array $modelUrls = [
        'https://verda-m.ru/catalog/dveri-loyard/dveri-vinyl-emalit/sevilya-07/'
    ];
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
        $page = 1;
        foreach ($this->urls as $url) {
            $load = true;
            /*            while ($load){
                            $categoryUrl = $url . '?PAGEN_1=' . $page;
                            $this->error($categoryUrl);
                            $html = file_get_contents($categoryUrl);
                            $crawler = new Crawler($html);
                            $this->category = $crawler->filter('h1')->text();
                            $load = $this->getModelUrls($crawler);
                            $page++;
                        }*/

            foreach ($this->modelUrls as $modelUrl) {
                $html = file_get_contents($modelUrl);
                $crawler = new Crawler($html);
                $this->getFilters($crawler);
                exit;
            }
        }
    }

    private function getModelUrls(Crawler $crawler)
    {
        $modelNodes = $crawler->filter('a.item-catalog');
        foreach ($modelNodes as $modelNode) {
            $modelCrawler = new Crawler($modelNode);
            $url = $modelCrawler->attr('href');
//            $this->info($url);
            if (in_array($url, $this->modelUrls)) {
                return false;
            }
            $this->modelUrls[] = $url;
        }

        return true;
    }

    private function getFilters(Crawler $crawler)
    {
        $nodes = $crawler->filter('div.card-section-wrap');
        foreach ($nodes as $node) {
            $filterCrawler = new Crawler($node);
            $titleNode = $filterCrawler->filter('div.section-title');
            if (count($titleNode) == 0) {
                continue;
            }
            switch ($titleNode->text()) {
                case 'Тип:':
                    $filterNodes = $filterCrawler->filter('div.frm-select-parameter');
                    $this->getFilterTypes($filterNodes);
                    break;
                case 'Размер:':
                    $filterNodes = $filterCrawler->filter('div.frm-select-parameter');
                    $this->getFilterCanvasSizes($filterNodes);
                    break;
                case 'Цвет:':
                    $filterNodes = $filterCrawler->filter('div.frm-select-color');
                    $this->getFilterColors($filterNodes);
                    break;
                default:
                    break;
            }
        }
        dd($this->filterTypes, $this->filterCanvasSizes, $this->filterColors);
    }

    private function getFilterTypes($nodes)
    {
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            if ($crawler->text() == 'Глухое') {
                continue;
            }
            $this->filterTypes[] = $crawler->text();
        }
    }

    private function getFilterColors($nodes)
    {
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $name = $crawler->filter('img')->attr('alt');
            $image = $crawler->filter('img')->attr('src');
            $this->filterColors[$name] = $image;
        }
    }

    private function getFilterCanvasSizes($nodes)
    {
        foreach ($nodes as $node) {
            $crawler = new Crawler($node);
            $this->filterCanvasSizes[] = $crawler->text();
        }
    }
}

