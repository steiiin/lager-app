<?php

namespace App\Console\Commands;

use App\Services\InventoryMaintenanceService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

class InventoryJobs extends Command
{
  protected $signature = 'inventory:jobs';

  protected $description = 'Clean old inventory data, vacuum the database and aggregate weekly statistics';

  public function handle(InventoryMaintenanceService $service): int
  {
    $result = $service->run();
    $this->output->writeln(
      json_encode($result, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
      OutputInterface::OUTPUT_RAW
    );

    return $result['ok'] ? self::SUCCESS : self::FAILURE;
  }
}
