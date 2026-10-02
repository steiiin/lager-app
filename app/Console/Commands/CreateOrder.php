<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

class CreateOrder extends Command
{
  protected $signature = 'order:create';

  protected $description = 'Send demand mail and create orders for items needing restock';

  public function handle(OrderService $service): int
  {
    $result = $service->create();
    $this->output->writeln(
      json_encode($result, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
      OutputInterface::OUTPUT_RAW
    );

    return $result['ok'] ? self::SUCCESS : self::FAILURE;
  }
}
