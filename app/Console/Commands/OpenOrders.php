<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

class OpenOrders extends Command
{
  protected $signature = 'order:open';

  protected $description = 'Show currently open orders';

  public function handle(OrderService $service): int
  {
    $result = $service->open();
    $this->output->writeln(
      json_encode($result, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
      OutputInterface::OUTPUT_RAW
    );

    return $result['ok'] ? self::SUCCESS : self::FAILURE;
  }
}
