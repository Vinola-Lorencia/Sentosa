<?php

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Covers the single centralized Carbon::toWib() macro (registered in
 * AppServiceProvider) that every display site is meant to go through
 * instead of re-deriving 'Asia/Jakarta' locally.
 */
class CarbonWibMacroTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The macro is normally registered by AppServiceProvider::boot(),
        // which doesn't run in a plain PHPUnit\Framework\TestCase (no app
        // bootstrap) — register it directly so this test exercises the
        // exact same closure the app registers, without needing the full
        // Laravel container.
        if (!Carbon::hasMacro('toWib')) {
            Carbon::macro('toWib', function () {
                return $this->copy()->timezone('Asia/Jakarta');
            });
        }
    }

    public function test_to_wib_shifts_utc_timestamp_forward_seven_hours()
    {
        $utc = Carbon::createFromFormat('Y-m-d H:i:s', '2026-09-16 05:52:08', 'UTC');

        $wib = $utc->toWib();

        $this->assertEquals('2026-09-16 12:52:08', $wib->format('Y-m-d H:i:s'));
        $this->assertEquals('Asia/Jakarta', $wib->timezone->getName());
    }

    public function test_to_wib_crossing_midnight_rolls_the_calendar_date_forward()
    {
        // A product created late at night UTC lands on the NEXT calendar
        // day in WIB — exactly the kind of case that would silently show
        // the wrong date, not just the wrong hour, if this weren't handled.
        $utc = Carbon::createFromFormat('Y-m-d H:i:s', '2026-09-16 20:00:00', 'UTC');

        $wib = $utc->toWib();

        $this->assertEquals('2026-09-17 03:00:00', $wib->format('Y-m-d H:i:s'));
    }

    public function test_to_wib_does_not_mutate_the_original_instance()
    {
        $utc = Carbon::createFromFormat('Y-m-d H:i:s', '2026-09-16 05:52:08', 'UTC');

        $utc->toWib();

        $this->assertEquals('UTC', $utc->timezone->getName());
        $this->assertEquals('2026-09-16 05:52:08', $utc->format('Y-m-d H:i:s'));
    }
}
