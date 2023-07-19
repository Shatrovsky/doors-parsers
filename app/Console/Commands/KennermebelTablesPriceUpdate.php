<?php

namespace App\Console\Commands;

use App\Helpers\SlugHelper;
use App\Models\Product;
use Illuminate\Console\Command;
use Symfony\Component\DomCrawler\Crawler;

class KennermebelTablesPriceUpdate extends Command
{
    const URL = 'https://kennermebel.ru';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'price:kennermebel-tables';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсинг https://kennermebel.ru/';

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
        $source = fopen('KENNER-shop_data-19.07.2023-Utf8.csv', 'r');
        $destination = fopen('KENNER-shop_data-19.07.2023.csv', 'w');
        $j = 0;
        while ($line = fgetcsv($source, 0, ";")) {
            if ($j > 0) {
                $url = $line[4];
                $this->info($url);
                try {
                    $html = file_get_contents($url);
                    preg_match("/'products': \[([^<]+)\]/", $html, $matches);
                    $data = json_decode($matches[1]);
                    $line[5] = (int)$data->price;
                } catch (\Exception $exception) {
                    $this->warn("Цена по товару {$url} не загружена");
                }
            }
            $j++;
            fputcsv($destination, $line, ";");
        }
    }
}
