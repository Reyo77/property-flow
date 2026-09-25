<?php

/**
 * Mutation testing for the finance module: `composer test:mutate`.
 *
 * Each core class is mutated against the test file written for it, rather than against every
 * test that happens to touch it. That keeps a run to minutes instead of hours (a mutant in the
 * ledger would otherwise re-run hundreds of database-backed tests) and asks the right question:
 * do *this class's own tests* pin its behaviour down?
 *
 * Runs sequentially on purpose — with --parallel, database set-up failures in the workers are
 * counted as "killed" mutants and the score is a meaningless 100%.
 *
 * Uses the loaded coverage driver (pcov in CI). Locally, if none is loaded, Herd's bundled
 * Xdebug is loaded for these runs only, without changing any global PHP config.
 */
$minimumScore = 80;

$targets = [
    'tests/Unit/Finance' => ['App\Support\Finance\Money'],
    'tests/Feature/Finance/LedgerTest.php' => [
        'App\Actions\Finance\PostJournalEntry',
        'App\Actions\Finance\ReverseJournalEntry',
        'App\Support\Finance\JournalLine',
    ],
    'tests/Feature/Finance/ReceivablesTest.php' => [
        'App\Actions\Finance\IssueInvoice',
        'App\Actions\Finance\RecordPayment',
        'App\Actions\Finance\ReversePayment',
        'App\Actions\Finance\VoidInvoice',
        'App\Actions\Finance\AllocateUnitCredits',
    ],
    'tests/Feature/Finance/BillingRunTest.php' => ['App\Actions\Finance\RunBilling'],
    'tests/Feature/Finance/LateFeesTest.php' => [
        'App\Actions\Finance\AssessLateFees',
        'App\Actions\Finance\SendOverdueReminders',
    ],
    'tests/Feature/Finance/VendorBillsTest.php' => [
        'App\Actions\Finance\SubmitVendorBill',
        'App\Actions\Finance\DecideVendorBill',
        'App\Actions\Finance\PayVendorBill',
    ],
    'tests/Feature/Finance/BankReconciliationTest.php' => [
        'App\Support\Finance\BankStatementParser',
        'App\Support\Finance\BankReconciliation',
    ],
    'tests/Feature/Finance/ReportsTest.php' => ['App\Support\Finance\FinancialReports'],
];

$php = escapeshellarg(PHP_BINARY);
$herdXdebug = '/Applications/Herd.app/Contents/Resources/xdebug/xdebug-84-arm64.so';

if (! extension_loaded('pcov') && ! extension_loaded('xdebug') && is_file($herdXdebug)) {
    $php .= ' -d zend_extension='.escapeshellarg($herdXdebug).' -d xdebug.mode=coverage';
}

$only = $argv[1] ?? null;
$failed = [];

foreach ($targets as $tests => $classes) {
    if ($only !== null && ! str_contains($tests, $only)) {
        continue;
    }

    echo PHP_EOL."▶ {$tests}".PHP_EOL.'  '.implode(PHP_EOL.'  ', $classes).PHP_EOL;

    $command = sprintf(
        '%s -d memory_limit=2G vendor/bin/pest %s --mutate --class=%s --min=%d',
        $php,
        escapeshellarg($tests),
        escapeshellarg(implode(',', $classes)),
        $minimumScore,
    );

    passthru($command, $exitCode);

    if ($exitCode !== 0) {
        $failed[] = $tests;
    }
}

if ($failed !== []) {
    fwrite(STDERR, PHP_EOL.'Mutation score below '.$minimumScore.'% (or failures) for:'.PHP_EOL.'  '.implode(PHP_EOL.'  ', $failed).PHP_EOL);
    exit(1);
}

echo PHP_EOL.'All finance mutation targets scored at least '.$minimumScore.'%.'.PHP_EOL;
