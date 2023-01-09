<?php

namespace App\Console\Commands;

use App\Models\VfdProduct;
use http\Exception\BadUrlException;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class Vfd extends Command
{
    const URL = 'https://vfd.ru';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsing:vfd';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсинг https://vfd.ru';
    const DATA_URL = 'https://vfd.ru/business/export/json/6';
    protected array $dataCategories = [];
    protected array $dataColors = [];
    protected array $dataInsertions = [];
    protected array $dataSets = [];
    protected array $dataFeaturesValues = [];
    protected array $dataFeatures = [];
    protected array $dataAccessories = [];
    protected $file;

    private array $categories = [
        1, 4, 5, 2, 22
    ];
    private array $excludeSubCategories1 = [6];
    private array $subCategories1 = [];
    private array $subCategories2 = [];
    protected string $category = '';
    protected string $subCategory1 = '';
    protected string $subCategory2 = '';
    private array $modelUrls = [];
    private string $modelUrl;
    private array $filterCanvasSizes = [];
    private array $filterColors = [];
    private array $filterGlasses = [];
    private array $modelImages = [];
    private array $offers = [];
    private array $prices = [];
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
        $this->file = fopen('vfd.csv', 'w');
        fputcsv($this->file, VfdProduct::$headers, "\t");
        $data = json_decode(file_get_contents(self::DATA_URL), true);
        $this->loader($data);
        foreach ($data['products'] as $product) {
        }
        foreach ($this->categories as $category) {
            $categoryId = $category['id'];
            $this->category = $category['title'];
            foreach ($this->subCategories1 as $subCategories1) {

            }
        }
    }

    private function getSubCategories1(int $categoryId)
    {
    }

    private function loader(array $data)
    {
        $this->dataCategories = $this->getDataCategories($data['categories']);
        $this->dataColors = $data['colors'];
        $this->dataInsertions = $data['insertions'];
        $this->dataSets = $data['sets'];
        $this->dataFeatures = $data['features'];
        $this->dataFeaturesValues = $data['featuresValues'];
        $this->dataAccessories = $data['accessories'];
    }

    private function getCategories(int $categoryId)
    {
        $categories = [];
        while (true) {
            if (!array_key_exists($categoryId, $this->dataCategories)) {
                return false;
            }
            $categories[] = $this->dataCategories[$categoryId];
            if ($this->dataCategories[$categoryId]['parent_id'] == 0) {
                break;
            }
            $categoryId = $this->dataCategories[$categoryId]['parent_id'];
        }
        return $categories;
    }

    private function getDataCategories(array $data)
    {
        foreach ($data as $item) {
            $this->dataCategories['id'] = $item;
        }
    }

    private function checkCategory(array $categories)
    {
    }
}

