<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

class RevertOrders extends Command
{
  protected $signature = 'order:revert';

  protected $description = 'Unlink bookings and remove all currently open orders';

  public function handle(OrderService $service): int
  {
    $result = $service->revert();
    $this->output->writeln(
      json_encode($result, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
      OutputInterface::OUTPUT_RAW
    );

    return $result['ok'] ? self::SUCCESS : self::FAILURE;
  }
}
