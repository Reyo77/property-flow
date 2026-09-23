<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads a unit spreadsheet as rows keyed by their snake_case column headings.
 */
class UnitRowsImport implements Import, WithHeadingRow {}
