<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BanVoteService;
use Illuminate\Console\Command;

class CloseBanCasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vesper:close-ban-cases';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Decide and close ban votes whose 72-hour voting window has ended';

    /**
     * Execute the console command.
     */
    public function handle(BanVoteService $bans): int
    {
        $closed = $bans->closeDue();

        $this->info("Closed {$closed} ban vote(s).");

        return self::SUCCESS;
    }
}
