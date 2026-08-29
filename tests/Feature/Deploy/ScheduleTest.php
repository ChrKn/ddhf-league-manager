<?php

namespace Tests\Feature\Deploy;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * What the one crontab line on the server actually drives.
 *
 * Shared hosting has nowhere to keep a process running, so the queue worker is not started by hand
 * and left alone - cron starts a short one every minute. That makes it a line in routes/console.php
 * like any other, and a line that can be lost in an edit without anything breaking loudly: imports
 * and exports would simply be accepted and never finish, and the password mail would never leave.
 */
class ScheduleTest extends TestCase
{
    private function entryFor(string $command): Event
    {
        $found = array_values(array_filter(
            app(Schedule::class)->events(),
            fn (Event $event) => str_contains($event->command ?? '', $command),
        ));

        $this->assertCount(1, $found, "Genau ein Eintrag für {$command} wird erwartet.");

        return $found[0];
    }

    public function test_the_queue_is_worked_every_minute(): void
    {
        $this->assertSame('* * * * *', $this->entryFor('queue:work')->expression);
    }

    public function test_the_worker_does_not_outlive_the_next_minute(): void
    {
        // Without these two a run started at 12:00 is still going at 12:01, and the workers pile up
        // one per minute until the account's process limit stops them - the failure mode the whole
        // arrangement exists to avoid.
        $entry = $this->entryFor('queue:work')->command;

        $this->assertStringContainsString('--stop-when-empty', $entry);
        $this->assertStringContainsString('--max-time=', $entry);
    }

    public function test_a_long_run_does_not_get_a_second_worker_on_top(): void
    {
        // An export can take longer than a minute. Then the next tick skips rather than doubles.
        $this->assertTrue($this->entryFor('queue:work')->withoutOverlapping);
    }

    public function test_a_worker_that_was_killed_does_not_block_the_queue_for_a_day(): void
    {
        // The lock holds for 1440 minutes unless told otherwise, and a worker the host kills never
        // releases it. That would stop the queue until tomorrow without a word being said.
        $this->assertLessThanOrEqual(10, $this->entryFor('queue:work')->expiresAt);
    }

    public function test_finished_exports_are_still_pruned_daily(): void
    {
        $this->assertSame('0 0 * * *', $this->entryFor('model:prune')->expression);
    }
}
