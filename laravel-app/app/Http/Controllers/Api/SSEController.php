<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SSEController extends Controller
{
    public function stream(Request $request)
    {
        $lastId = (int) ($request->query('lastEventId') ?? $request->query('last_id') ?? 0);

        return response()->stream(function () use ($lastId) {
            // Disable output buffering
            if (ob_get_level()) ob_end_flush();
            set_time_limit(0);
            ignore_user_abort(false);

            echo "retry: 3000\n\n";
            flush();

            $maxId    = $lastId;
            $deadline = time() + 25; // 25s then close so client reconnects

            while (time() < $deadline) {
                if (connection_aborted()) break;

                $events = DB::table('sse_events')
                    ->where('id', '>', $maxId)
                    ->orderBy('id')
                    ->limit(20)
                    ->get();

                foreach ($events as $event) {
                    echo "id: {$event->id}\n";
                    echo "event: {$event->event_type}\n";
                    echo "data: {$event->payload}\n\n";
                    $maxId = max($maxId, $event->id);
                }

                if ($events->isNotEmpty()) flush();

                usleep(800000); // 0.8s poll
            }

            // Send keepalive then let client reconnect
            echo ": keepalive\n\n";
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }
}
