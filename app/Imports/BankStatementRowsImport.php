<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Reads a bank statement export as rows keyed by their snake_case column headings. Every cell is
 * kept as the text the bank wrote, so amounts are parsed as money (never floats) and dates in the
 * bank's own format.
 */
class BankStatementRowsImport extends StringValueBinder implements Import, WithCustomValueBinder, WithHeadingRow {}
