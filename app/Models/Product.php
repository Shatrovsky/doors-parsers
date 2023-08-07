<?php


namespace App\Models;


class Product
{
    public $category = 'Столы';
    public $subCategory = '';
    public $id = '';
    public $name = '';
    public $nameUrl = '';
    public $url = '';
    public $shortDescription = '';
    public $description = '';
    public $published = 'выставлен';
    public $isDiscount = 'да';
    public $metaTitle = '';
    public $metaKeywords = '';
    public $metaDescription = '';
    public $sitePlace = '';
    public $weightCoefficient = '';
    public $currency = 'RUR';
    public $nds = 'Без НДС';
    public $unit = 'шт';
    public $dimensions = '';
    public $image = '';
    public $videoUrl = '';
    public $variantId = '';
    public $artikul = '';
    public $barcode = '';
    public $variantDimensions = '';
    public $price = 1000;
    public $oldPrice = '';
    public $netto = '';
    public $count = 100;
    public $weight = '';
    public $variantUrl = '';
    public $manufacturer = 'ДИК ';
    public $parsingUrl = '';
    public $width = '';
    public $depth = '';
    public $altitude = '';
    public $supportColor = '';
    public $tableMaterial = '';
    public $supportMaterial = '';
    public $carcasMaterial = '';
    public $warranty = '';
    public $maxWeight = '';
    public $tableForm = '';
    public $mechanismType = '';

    public static $headers = [
        'Категория',
        'Подкатегория',
        'ID товара',
        'Название товара или услуги',
        'Название товара в URL',
        'URL',
        'Краткое описание',
        'Полное описание',
        'Видимость на витрине',
        'Применять скидки',
        'Тег title',
        'Мета-тег keywords',
        'Мета-тег description',
        'Размещение на сайте',
        'Весовой коэффициент',
        'Валюта склада',
        'НДС',
        'Единица измерения',
        'Габариты',
        'Изображения',
        'Ссылка на видео',
        'ID варианта',
        'Артикул',
        'Штрих-код',
        'Габариты варианта',
        'Цена продажи',
        'Старая цена',
        'Цена закупки',
        'Остаток',
        'Вес',
        'Изображения варианта',
        'Параметр: Производитель',
        'Параметр: Ссылка на донер',

        'Параметр: Ширина',
        'Параметр: Глубина',
        'Параметр: Высота',
        'Параметр: Цвет опор',
        'Параметр: Материал столешницы',
        'Параметр: Материал опоры',
        'Параметр: Материал каркаса',
        'Параметр: Гарантийный срок',
        'Параметр: Максимальная нагрузка',
        'Параметр: Форма столешницы',
        'Параметр: Механизм раскладки',
    ];

    public function exportCsv($file)
    {
        $data = (array) $this;
        fputcsv($file, $data, "\t");
    }
}
