<?php

namespace App\Providers;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Region the app displays timestamps in, for the toWib() macro below.
     * Not the same as config('app.timezone') (kept as UTC on purpose — see
     * the macro's own docblock).
     */
    public const DISPLAY_TIMEZONE = 'Asia/Jakarta';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Single centralized place to convert a stored (UTC) timestamp to
        // WIB for display. config('app.timezone') deliberately stays 'UTC'
        // — the products/sales/etc. `timestamp without time zone` columns
        // hold naive UTC literals, and changing app.timezone would change
        // how Laravel WRITES new timestamps too (not just how it reads
        // them), silently mixing UTC and WIB literals in the same column
        // for old vs. new rows. Converting only at the point of display,
        // via this macro, avoids touching stored data or write behavior at
        // all. Every place that shows a created_at/updated_at (or similar)
        // to a user should go through this rather than re-deriving the
        // 'Asia/Jakarta' timezone locally.
        Carbon::macro('toWib', function (): CarbonInterface {
            /** @var CarbonInterface $this */
            return $this->copy()->timezone(AppServiceProvider::DISPLAY_TIMEZONE);
        });
    }
}
