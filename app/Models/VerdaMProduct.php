<?php


namespace App\Models;


class VerdaMProduct
{
    public $category = '';
    public $subCategory1 = '';
    public $subCategory2 = '';
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
    public $canvasSize = '';
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
    public $manufacturer = 'Двери Верда';
    public $parsingUrl = '';
    public $color = '';
    public $glass = '';
    public $model = '';
    public $insert = '';
    public $edge = '';
    public $supplierArticul = '';
    public $innerArticul = '';

    public static $headers = [
        'Корневая',
        'Подкатегория1',
        'Подкатегория2',
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
        'Свойство: Размер полотна',
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
        'Параметр: Цвет',
        'Параметр: Стекло',
        'Параметр: Модель',
        'Параметр: Вставка',
        'Параметр: Кромка',
        'Параметр: Артикул поставщика',
        'Параметр: Суррогатный артикул',
    ];

    public function exportCsv($file)
    {
        $data = (array) $this;
        fputcsv($file, $data, "\t");
    }

    public function setInnerArticul()
    {
        $string = $this->name.$this->canvasSize.$this->color.$this->glass;
        $this->innerArticul = md5($string);
    }
}