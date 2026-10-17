<?php

namespace App\Console\Commands;

use App\Actions\SendAlerts;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Emails every buyer one message with all their deliverable alerts.
 *
 * Walks buyers by id rather than paging through alerts: alerts are marked
 * sent as we go, so offset paging over them would skip rows.
 *
 * Scheduled daily in routes/console.php. Nothing runs the scheduler in this
 * exercise, so run it by hand to see the digest in the log.
 */
class SendAlertDigests extends Command
{
    protected $signature = 'alerts:send-digests';

    protected $description = 'Email each buyer one digest of their unsent alerts';

    public function handle(SendAlerts $sendAlerts): int
    {
        $sent = 0;

        User::query()
            ->whereIn('id', Alert::query()->deliverable()->select('user_id'))
            ->chunkById(500, function (Collection $buyers) use ($sendAlerts, &$sent): void {
                foreach ($buyers as $buyer) {
                    /** @var User $buyer */
                    $sendAlerts->handle($buyer, $buyer->alerts()->deliverable()->orderBy('id')->get());
                    $sent++;
                }
            });

        $this->components->info("Sent {$sent} digest email(s).");

        return self::SUCCESS;
    }
}
