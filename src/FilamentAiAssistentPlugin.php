<?php

declare(strict_types=1);

namespace Bramato\FilamentAiAssistent;

use BezhanSalleh\FilamentShield\Support\Utils;
use Bramato\FilamentAiAssistent\Resources\StripePlanResource;
use Bramato\FilamentAiAssistent\Resources\StripeProductResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentAiAssistentPlugin implements Plugin
{

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-stripe-manager';
    }

    public function register(Panel $panel): void
    {

            $panel->resources([
            ])
            ;

    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
