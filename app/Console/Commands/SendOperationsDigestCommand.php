<?php

namespace App\Console\Commands;

use App\Mail\AdminOperationsDigestNotification;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOperationsDigestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vesper:send-operations-digest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compile administrative operational metrics and dispatch daily digest to administrators';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $admins = User::where('role', 'admin')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        if ($admins->isEmpty()) {
            $this->info('No administrators registered. Skipping operations digest.');

            return self::SUCCESS;
        }

        $digestStats = [
            'active_channels_count' => Room::where('status', 'active')->count(),
            'total_members_count' => User::count(),
            'total_operatives_count' => User::count(),
            'total_messages_count' => Message::count(),
            'security_incidents_count' => 0,
        ];

        $dispatched = 0;
        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(new AdminOperationsDigestNotification($admin, $digestStats));
                $dispatched++;
            } catch (\Throwable $e) {
                Log::warning("Failed to dispatch operations digest to [{$admin->email}]: {$e->getMessage()}");
            }
        }

        $this->info("Dispatched operational digest to {$dispatched} administrator(s).");

        return self::SUCCESS;
    }
}
