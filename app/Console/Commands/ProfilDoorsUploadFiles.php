<?php

namespace App\Console\Commands;

use App\Models\ProfilProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\DomCrawler\Crawler;

class ProfilDoorsUploadFiles extends Command
{
    const URL = 'http://pfdpics.tw1.ru/';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upload:profil-doors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Загрузка фото на сервер';

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
        $source = fopen('profilDoorsUtf8_1.csv', 'r');
        $destination = fopen('profilDoorsFiles_1.csv', 'w');
        $i = 0;
        $fileUpload = '';
        $fileUploaded = '';
        while ($line = fgetcsv($source, 0, ";")) {
            if ($i > 0) {
                for ($i = 0; $i < 3; $i++) {
                    if ($fileUpload != $line[20] && empty($line[42])) {
                        $fileUpload = $line[20];
                        $fileUploaded = $this->uploadFile($line[20], $line[38]);
                        if ($fileUploaded != '') {
                            break;
                        }
                    }
                }
                $line[42] = $fileUploaded;
            } else {
                $line[42] = 'Новое фото';
            }
            fputcsv($destination, $line);
            $i++;
        }
    }

    private function uploadFile(string $url, string $model): string
    {
        $this->alert("Загружается файл: " . $url);
        $fileName = md5(microtime()) . ".jpg";
        $tempName = "profilDoors/" . $fileName;
        $uploadFile = '/images/public_html/' . $model . "/" . $fileName;
        try {
            $arrayFile = explode(".", $url);
            if (Arr::last($arrayFile) == 'png') {
                $input = imagecreatefrompng($url);
                $width = imagesx($input);
                $height = imagesy($input);
                $output = imagecreatetruecolor($width, $height);
                $white = imagecolorallocate($output, 255, 255, 255);
                imagefilledrectangle($output, 0, 0, $width, $height, $white);
                imagecopy($output, $input, 0, 0, 0, 0, $width, $height);
                imagejpeg($output, $tempName);
                $this->info("Сохранен файл: " . $tempName);
                $content = file_get_contents($tempName);
            } else {
                $content = file_get_contents($url);
            }
            Storage::disk('ftp')->put($uploadFile, $content);
            $this->info("Загружен файл: " . $tempName);
            $fileUploaded = self::URL . $model . "/" . $fileName;
            $headers = @get_headers($fileUploaded);
            if (strpos($headers[0], '200') != false) {
                $this->warn("Файл {$fileUploaded} загружен");
            } else {
                $this->warn("Файл {$fileUploaded} не загружен");
            }
        } catch (\Exception $exception) {
            Log::channel('profilDoors')->error("Файл {$url} не загружен");
            Log::channel('profilDoors')->error("Временный файл {$tempName}");
            Log::channel('profilDoors')->error("Фтп файл {$uploadFile}");
            $this->error("Файл {$url} не загружен");
            $fileUploaded = '';
        }

        return $fileUploaded;
    }

    private function pngToJpg(string $source)
    {
    }

    private function getContent(string $filename)
    {
        $agent = $this->userAgent();
        $opts = [
            'http' => [
                'method' => "GET",
                'header' => "Accept-Language: en-US,en;q=0.9,ru;q=0.8\r\n" .
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.50 Safari/537.36 OPR/65.0.3467.16 (Edition beta)\r\n"]
        ];

        $context = stream_context_create($opts);
        for ($i = 0; $i < 3; $i++) {
            try {
                $ch = curl_init();
                $header = array('GET /1575051 HTTP/1.1',
                    'Accept:text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language:en-US,en;q=0.8',
                    'Cache-Control:max-age=0',
                    'Connection:keep-alive',
                    'Host:adfoc.us',
                    'User-Agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.116 Safari/537.36',
                );
                curl_setopt($ch, CURLOPT_URL, $filename);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                curl_setopt($ch, CURLOPT_COOKIESESSION, true);
                curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
                curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                $result = curl_exec($ch);
                curl_close($ch);


                return $result;
            } catch (\Exception $exception) {
                $this->error("Ошибка загрузки файла {$filename} попытка {$i}");
            }
        }
        return false;
    }

    /**
     * Return string for User-Agent, example: http://livedune.ru; igor.k@livedune.ru
     * @return string
     */
    public function userAgent(): string
    {
        $agents = [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:76.0) Gecko/20100101 Firefox/76.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:76.0) Gecko/20100101 Firefox/76.0',
            'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:76.0) Gecko/20100101 Firefox/76.0',
            'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.129 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; rv:68.0) Gecko/20100101 Firefox/68.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.129 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:75.0) Gecko/20100101 Firefox/75.0',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:76.0) Gecko/20100101 Firefox/76.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1.1 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64; rv:76.0) Gecko/20100101 Firefox/76.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:76.0) Gecko/20100101 Firefox/76.0',
            'Mozilla/5.0 (X11; Linux x86_64; rv:68.0) Gecko/20100101 Firefox/68.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.129 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36 OPR/68.0.3618.125',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.5 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.102 Safari/537.36 Edge/18.18363',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.129 Safari/537.36 OPR/68.0.3618.63',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 11_3_1 like Mac OS X) AppleWebKit/603.1.30 (KHTML, like Gecko) Version/10.0 Mobile/14E304 Safari/602.1',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 10_3_1 like Mac OS X) AppleWebKit/603.1.30 (KHTML, like Gecko) Version/10.0 Mobile/14E304 Safari/602.1',
        ];

        return $agents[rand(0, count($agents) - 1)];
    }

    private function getContentHttp(string $url)
    {
        $headers = [
            'Host' => 'profildoors.ru',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/113.0',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
        ];
        $response = Http::withHeaders($headers)->get($url);

        return $response->body();
    }
}

