<?php

namespace App\Console\Commands;

use App\Mail\ChannelBurnWarningNotification;
use App\Models\Room;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ChannelBurnCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vesper:channel-burn-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan channels for TTL expiration, execute media incineration, and send burn warnings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();

        // 1. Incinerate channels that have passed their TTL
        $expiredRooms = Room::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        $incineratedCount = 0;
        foreach ($expiredRooms as $room) {
            try {
                $room->purgeAllMedia();
                $room->update(['status' => 'destroyed']);
                $incineratedCount++;
                Log::info("Vesper Protocol: Channel [{$room->code}] expired and media was incinerated.");
            } catch (\Throwable $e) {
                Log::error("Failed to incinerate channel [{$room->code}]: {$e->getMessage()}");
            }
        }

        $this->info("Incinerated {$incineratedCount} expired channel(s).");

        // 2. Scan active channels expiring in <= 2 hours for burn warning dispatch
        $imminentRooms = Room::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $now->copy()->addHours(2))
            ->get();

        $warningsDispatched = 0;
        foreach ($imminentRooms as $room) {
            $cacheKey = "channel_burn_warned_{$room->id}";
            if (Cache::has($cacheKey)) {
                continue;
            }

            $hoursRemaining = max(1, (int) round($room->expires_at->diffInMinutes($now) / 60));

            // Find all enrolled members who have email notifications active or admins
            $members = $room->members()
                ->where('email_notifications', true)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->get();

            if ($room->notify_admin) {
                $admins = User::where('role', 'admin')
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->get();
                $members = $members->merge($admins)->unique('id');
            }

            foreach ($members as $recipient) {
                try {
                    Mail::to($recipient->email)->send(
                        new ChannelBurnWarningNotification($recipient, $room, (string) $hoursRemaining)
                    );
                    $warningsDispatched++;
                } catch (\Throwable $e) {
                    Log::warning("Failed to dispatch burn warning for channel [{$room->code}] to [{$recipient->email}]: {$e->getMessage()}");
                }
            }

            // Cache to prevent duplicate warnings for this room
            Cache::put($cacheKey, true, now()->addHours(3));
        }

        $this->info("Dispatched {$warningsDispatched} burn warning alert(s).");

        return self::SUCCESS;
    }
}
