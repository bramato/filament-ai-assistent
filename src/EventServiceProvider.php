<?php

namespace Bramato\FilamentAiAssistent;

use Bramato\FilamentAiAssistent\Events\BlogPublished;
use Bramato\FilamentAiAssistent\Events\ProductModelEvent;
use Bramato\FilamentAiAssistent\Events\StripeWebhookEvent;
use Bramato\FilamentAiAssistent\Listeners\CheckStripeCustomer;
use Bramato\FilamentAiAssistent\Listeners\ProductModelListener;
use Bramato\FilamentAiAssistent\Listeners\SendBlogPublishedNotification;
use Bramato\FilamentAiAssistent\Listeners\StripeWebhookCallListener;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [

    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }
}
