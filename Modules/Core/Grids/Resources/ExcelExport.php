<?php

namespace Modules\Core\Grids\Resources;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Style;

class ExcelExport extends DefaultValueBinder implements FromArray, ShouldAutoSize, WithDefaultStyles, WithHeadings, WithStrictNullComparison
{
    private array $headers = [];

    private array $data = [];

    public function __construct(array $headers, array $data)
    {
        $this->headers = $headers;
        $this->data = $data;
    }

    public function headings(): array
    {
        return $this->headers;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function defaultStyles(Style $defaultStyle)
    {
        // Configure the default styles
        return $defaultStyle->getFill()->setFillType(Fill::FILL_SOLID);
    }
}
