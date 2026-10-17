<?php

namespace Tests\Feature;

use App\Actions\SendAlerts;
use App\Models\Alert;
use App\Notifications\AlertNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerts_go_out_as_one_email_and_are_marked_sent(): void
    {
        Notification::fake();
        $buyer = $this->demoUser();
        $alerts = Alert::factory(3)->for($buyer)->create();

        app(SendAlerts::class)->handle($buyer, $alerts);

        Notification::assertSentToTimes($buyer, AlertNotification::class, 1);
        $this->assertSame(0, Alert::query()->unsent()->count());
    }

    public function test_nothing_is_sent_for_no_alerts(): void
    {
        Notification::fake();

        app(SendAlerts::class)->handle($this->demoUser(), new Collection);

        Notification::assertNothingSent();
    }
}
