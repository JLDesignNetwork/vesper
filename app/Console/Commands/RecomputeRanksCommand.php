<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RankService;
use Illuminate\Console\Command;

class RecomputeRanksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vesper:recompute-ranks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recompute Admin III, User I and User II ranks from each member\'s qualifying channels';

    /**
     * Execute the console command.
     */
    public function handle(RankService $rankService): int
    {
        $changed = $rankService->recomputeAll();

        $this->info("Recomputed ranks. {$changed} member(s) changed rank.");

        return self::SUCCESS;
    }
}
