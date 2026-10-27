<?php

namespace App\Console\Commands;

use App\Models\TransferRequest;
use Illuminate\Console\Command;

class ExpirePendingTransfers extends Command
{
    protected $signature = 'transfers:expire';

    protected $description = 'Marca como expiradas las transferencias pending pasadas de 48h';

    public function handle(): int
    {
        $count = TransferRequest::where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $this->info("Transferencias expiradas: {$count}");
        return self::SUCCESS;
    }
}