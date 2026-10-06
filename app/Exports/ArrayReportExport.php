<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ArrayReportExport implements FromArray, WithHeadings
{
    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    public function __construct(private readonly array $rows) {}

    public function headings(): array
    {
        return $this->rows[0] ?? [];
    }

    public function array(): array
    {
        return array_slice($this->rows, 1);
    }
}
