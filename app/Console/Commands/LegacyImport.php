<?php

namespace App\Console\Commands;

use App\Legacy\DryRunFinished;
use App\Legacy\Importer;
use App\Legacy\Importers\CompaniesImporter;
use App\Legacy\Importers\DocumentsImporter;
use App\Legacy\Importers\ExecutionImporter;
use App\Legacy\Importers\IsinImporter;
use App\Legacy\Importers\MastersImporter;
use App\Legacy\Importers\OrganisationImporter;
use App\Legacy\Importers\RolesImporter;
use App\Legacy\Importers\SecurityImporter;
use App\Legacy\Importers\TransactionsImporter;
use App\Legacy\ImportReport;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Brings data over from the legacy Stack database (read-only `legacy` connection). Each area runs
 * in one DB transaction: it either imports completely or not at all. --dry-run does the whole
 * import and then rolls it back, so the report shows exactly what a real run would do.
 */
class LegacyImport extends Command
{
    use ConfirmableTrait;

    protected $signature = 'legacy:import
        {area : organisation, roles, masters, companies, transactions, documents, execution, security, isin, or all (in that order)}
        {--dry-run : Run everything, report, then roll back}
        {--force : Run in production without asking}';

    protected $description = 'Import data from the legacy Stack database';

    /** @var list<class-string<Importer>> in dependency order */
    private const IMPORTERS = [
        OrganisationImporter::class,
        RolesImporter::class,
        MastersImporter::class,
        CompaniesImporter::class,
        TransactionsImporter::class,
        DocumentsImporter::class,
        ExecutionImporter::class,
        SecurityImporter::class,
        IsinImporter::class,
    ];

    public function handle(): int
    {
        $importers = array_map(fn (string $class) => app($class), self::IMPORTERS);
        $area = (string) $this->argument('area');
        $selected = $area === 'all' ? $importers : array_values(array_filter($importers, fn (Importer $i) => $i->area() === $area));

        if ($selected === []) {
            $this->error("Unknown area \"{$area}\". Use one of: ".implode(', ', array_map(fn (Importer $i) => $i->area(), $importers)).', all.');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! $this->confirmToProceed('This writes legacy data into the Nexora database.')) {
            return self::FAILURE;
        }

        // All selected areas run in one DB transaction: a later area sees what earlier ones imported
        // (also in a dry run), and a failure anywhere leaves the database exactly as it was.
        $reports = [];
        activity()->disableLogging(); // imported rows are history, not edits made in Nexora

        try {
            DB::transaction(function () use ($selected, $dryRun, &$reports) {
                foreach ($selected as $importer) {
                    $this->info(($dryRun ? '[dry run] ' : '')."Importing {$importer->area()}: {$importer->description()}…");
                    $report = $reports[] = new ImportReport($importer->area(), $dryRun);
                    $importer->run($report);
                }
                if ($dryRun) {
                    throw new DryRunFinished;
                }
            });
        } catch (DryRunFinished) {
            // Rolled back on purpose.
        } catch (Throwable $e) {
            $this->error('The import failed and nothing was saved: '.$e->getMessage());
            $this->line('  at '.$e->getFile().':'.$e->getLine(), verbosity: 'v');
            $this->line($e->getTraceAsString(), verbosity: 'vv');

            return self::FAILURE;
        } finally {
            activity()->enableLogging();
        }

        foreach ($reports as $report) {
            $this->render($report);
        }
        if ($dryRun) {
            $this->warn('Dry run: everything above was rolled back.');
        }

        return self::SUCCESS;
    }

    private function render(ImportReport $report): void
    {
        $this->table(
            ['Records', 'Read', 'Created', 'Updated', 'Unchanged', 'Rejected'],
            collect($report->counts())->map(fn (array $c, string $entity) => [$entity, $c['read'], $c['created'], $c['updated'], $c['unchanged'], $c['rejected']])->values()->all(),
        );

        foreach ($report->totals() as $label => $value) {
            $this->line("  {$label}: <info>{$value}</info>");
        }
        foreach (array_slice($report->rejections(), 0, 15) as $r) {
            $this->line("  <fg=red>rejected</> {$r['entity']} #{$r['legacy_id']}: {$r['reason']}");
        }
        foreach (array_slice($report->warnings(), 0, 15) as $w) {
            $this->line("  <fg=yellow>adjusted</> {$w['entity']} #{$w['legacy_id']}: {$w['note']}");
        }

        $path = 'legacy-import/'.now()->format('Y-m-d_His')."_{$report->area}".($report->dryRun ? '_dry-run' : '').'.json';
        Storage::disk('local')->put($path, (string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line(sprintf('  %d rejected, %d adjusted. Full report: storage/app/private/%s', count($report->rejections()), count($report->warnings()), $path));
        $this->newLine();
    }
}
