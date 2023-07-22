<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\DataProduct;
use App\Models\DveriComProduct;
use App\Models\Property;
use App\Models\PropertyValue;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\DomCrawler\Crawler;

class DveriComPriceUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'price-update:dveri-com';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Обновление цен';

    public array $priceList = [];
    public array $priceUrlList = [];

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
        $file = fopen('BRAVO-FURNITURA-shop_data-21.07.2023Utf8.csv', 'r');
        $fileOut = fopen('BRAVO-FURNITURA-shop_data-22.07.2023Utf8.csv', 'w');
        $priceList = $this->getContent();
        foreach ($priceList->products as $product) {
            $this->getPrice($product);
        }
        $line = fgetcsv($file, 0, ";");
        fputcsv($fileOut, $line, ";");
        while (($line = fgetcsv($file, 0, ";")) !== FALSE) {
            $vendorCode = $line[3];
            if (empty($vendorCode)) {
                $url = $line[5];
                if (!array_key_exists($url, $this->priceUrlList)) {
                    $this->info($url);
                } else {
                    $price = $this->priceUrlList[$url];
                    $line[7] = ceil($price['price_dealer']);
                    $line[6] = ceil($line[7] * 1.3 - 4);
                }
            } else {
                if (!array_key_exists($vendorCode, $this->priceList)) {
                    $this->error($vendorCode);
                } else {
                    $price = $this->priceList[$vendorCode];
                    $line[7] = ceil($price['price_dealer']);
                    $line[6] = ceil($line[7] * 1.3 - 4);
                }
            }
            fputcsv($fileOut, $line, ";");
        }
    }

    private function getPrice(\stdClass $product)
    {
        $price = [
            'discount_dealer' => $product->discount_dealer,
            'price_dealer' => $product->price_dealer,
        ];
        $vendorCode = $product->vendor_code;
        if (empty($vendorCode)) {
            $this->getUrlPrice($product);
        } else {
            if (!array_key_exists($vendorCode, $this->priceList)) {
                $this->priceList[$vendorCode] = $price;
            }
            if (!empty($product->options)) {
                $this->getPriceOptions($product->options);
            }
        }
    }

    private function getUrlPrice(\stdClass $product)
    {
        $price = [
            'discount_dealer' => $product->discount_dealer,
            'price_dealer' => $product->price_dealer,
        ];
        $url = $product->url;
        if (!array_key_exists($url, $this->priceUrlList)) {
            $this->priceUrlList[$url] = $price;
        }
    }

    private function getPriceOptions(array $options)
    {
        foreach ($options as $option) {
            $price = [
                'discount_dealer' => $option->discount_dealer,
                'price_dealer' => $option->price_dealer,
            ];
            $vendorCode = $option->vendor_code;
            if (!array_key_exists($vendorCode, $this->priceList)) {
                $this->priceList[$vendorCode] = $price;
            }
        }
    }

    private function getContent()
    {
        $source = 'https://dveri.com/export/json/moskva';
        $data = file_get_contents($source);
        return json_decode($data);
    }

}

