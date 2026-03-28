<?php

namespace App\Console\Commands;

use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Console\Command;

class RevertTemporaryTransfers extends Command
{
    protected $signature = 'transfers:revert-temporary';

    protected $description = 'Revert temporary transfers that have reached their expected return date';

    public function handle(TransferService $transferService): int
    {
        $transfers = Transfer::withoutGlobalScopes()
            ->where('transfer_type', 'TEMPORARY')
            ->where('status', 'COMPLETED')
            ->whereNotNull('expected_return_date')
            ->whereDate('expected_return_date', '<=', now())
            ->get();

        $count = 0;
        foreach ($transfers as $transfer) {
            $transferService->revertTransfer($transfer);
            $count++;
        }

        $this->info("Reverted {$count} temporary transfer(s).");

        return Command::SUCCESS;
    }
}
