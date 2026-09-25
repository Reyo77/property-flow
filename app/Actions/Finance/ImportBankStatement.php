<?php

namespace App\Actions\Finance;

use App\Enums\SystemAccount;
use App\Imports\BankStatementRowsImport;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Community;
use App\Models\User;
use App\Support\Finance\BankReconciliation;
use App\Support\Finance\BankStatementParser;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Imports a bank statement export for the community's bank account and matches what it can.
 */
class ImportBankStatement
{
    public function __construct(
        private readonly BankStatementParser $parser,
        private readonly ChartOfAccounts $chartOfAccounts,
        private readonly BankReconciliation $bankReconciliation,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(Community $community, UploadedFile $file, CarbonImmutable $startsOn, CarbonImmutable $endsOn, Money $closingBalance, User $importedBy): BankStatement
    {
        $rows = Excel::toArray(new BankStatementRowsImport, $file)[0] ?? [];
        $lines = $this->parser->parse(array_values($rows), $community->currency);

        foreach ($lines as $index => $line) {
            if ($line['posted_on']->lessThan($startsOn) || $line['posted_on']->greaterThan($endsOn)) {
                throw ValidationException::withMessages(['file' => __('Row :row is dated :date, outside the statement period.', ['row' => $index + 2, 'date' => $line['posted_on']->toDateString()])]);
            }
        }

        $statement = DB::transaction(function () use ($community, $file, $startsOn, $endsOn, $closingBalance, $importedBy, $lines): BankStatement {
            $statement = new BankStatement;
            $statement->forceFill([
                'company_id' => $community->company_id,
                'community_id' => $community->id,
                'account_id' => $this->chartOfAccounts->account($community, SystemAccount::Cash)->id,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
                'closing_balance_cents' => $closingBalance->cents,
                'filename' => mb_substr($file->getClientOriginalName(), 0, 255),
                'imported_by_id' => $importedBy->id,
            ])->save();

            foreach ($lines as $line) {
                (new BankStatementLine)->forceFill([
                    'company_id' => $community->company_id,
                    'bank_statement_id' => $statement->id,
                    'posted_on' => $line['posted_on']->toDateString(),
                    'description' => $line['description'],
                    'reference' => $line['reference'],
                    'amount_cents' => $line['amount_cents'],
                ])->save();
            }

            return $statement;
        });

        $this->bankReconciliation->autoMatch($statement);

        return $statement;
    }
}
