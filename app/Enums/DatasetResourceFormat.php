<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DatasetResourceFormat: string implements HasLabel
{
    case Csv = 'CSV';
    case Json = 'JSON';
    case Xml = 'XML';
    case Xls = 'XLS';
    case Xlsx = 'XLSX';
    case Ods = 'ODS';
    case Txt = 'TXT';
    case Pdf = 'PDF';
    case Html = 'HTML';
    case Rdf = 'RDF';
    case Zip = 'ZIP';

    public function getLabel(): string
    {
        return $this->value;
    }
}
