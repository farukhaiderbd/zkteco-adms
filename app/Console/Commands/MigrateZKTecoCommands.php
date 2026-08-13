<?php

namespace App\Console\Commands;

use App\Services\ZKTecoCommandService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('zkteco:migrate-commands')]
#[Description('Migrate pending commands from custom biometric system to Filament ZKTeco package')]
class MigrateZKTecoCommands extends Command
{
    protected $commandService;

    public function __construct(ZKTecoCommandService $commandService)
    {
        parent::__construct();
        $this->commandService = $commandService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting ZKTeco command migration...');

        try {
            $results = $this->commandService->migrateAllPendingCommands();

            $totalMigrated = array_sum($results);

            if ($totalMigrated > 0) {
                $this->info("Successfully migrated {$totalMigrated} commands:");

                foreach ($results as $serialNumber => $count) {
                    $this->info("  - Device {$serialNumber}: {$count} commands");
                }

                return self::SUCCESS;
            } else {
                $this->info('No pending commands found to migrate.');

                return self::SUCCESS;
            }
        } catch (\Exception $e) {
            $this->error("Migration failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
