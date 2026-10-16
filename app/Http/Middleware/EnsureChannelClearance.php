<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Room;
use App\Services\ChannelAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every in-channel endpoint (messages, reactions, attachments, radar, ...).
 *
 * Requires live clearance for the channel and re-checks that the member may still use
 * it, so idle timeouts, demotions and closed channels apply on the very next request.
 * Requests that change something count as member activity and extend clearance;
 * reads such as message polling do not.
 */
class EnsureChannelClearance
{
    public function __construct(
        protected ChannelAccessService $access
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $room = Room::where('code', strtoupper(trim((string) $request->route('room'))))->first();

        // Missing or closed channels are reported by the controllers themselves (410 / redirect).
        if (! $room || $room->status !== 'active') {
            return $next($request);
        }

        if (! $this->access->hasClearance($request, $room)) {
            return $this->refuse($request, $room, __('Your access to this channel has ended. Open it again to continue.'));
        }

        if (! $this->access->mayContinue($request->user(), $room)) {
            $this->access->revokeClearance($request, $room);

            return $this->refuse($request, $room, __('You no longer have access to this channel.'));
        }

        if (! $request->isMethodSafe()) {
            $this->access->touchClearance($request, $room);
        }

        return $next($request);
    }

    /**
     * Refuse with 403 for API calls, or send a page visit back to the channel gate.
     */
    protected function refuse(Request $request, Room $room, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Clearance required', 'message' => $message], 403);
        }

        abort(403, $message);
    }
}
