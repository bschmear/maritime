<?php

declare(strict_types=1);

namespace App\Domain\BoatShow\Support;

use App\Domain\BoatShow\Models\BoatShow;
use App\Domain\BoatShowEvent\Models\BoatShowEvent;
use App\Domain\Integration\Support\WordPressIntegrationSettings;
use App\Jobs\DeleteBoatShowEventFromWordPress;
use App\Jobs\DeleteBoatShowFromWordPress;
use App\Jobs\PushBoatShowEventToWordPress;
use App\Jobs\PushBoatShowToWordPress;

final class WordPressBoatShowSync
{
    public static function pushShow(BoatShow $show): void
    {
        if (! self::shouldAutoPush()) {
            return;
        }

        PushBoatShowToWordPress::dispatch($show->id);
    }

    public static function pushEvent(BoatShowEvent $event): void
    {
        if (! self::shouldAutoPush()) {
            return;
        }

        PushBoatShowEventToWordPress::dispatch($event->id);
    }

    public static function deleteShow(BoatShow $show): void
    {
        if (! self::shouldAutoPush()) {
            return;
        }

        DeleteBoatShowFromWordPress::dispatch($show->uuid);
    }

    public static function deleteEvent(BoatShowEvent $event): void
    {
        if (! self::shouldAutoPush()) {
            return;
        }

        DeleteBoatShowEventFromWordPress::dispatch($event->uuid);
    }

    private static function shouldAutoPush(): bool
    {
        $settings = WordPressIntegrationSettings::forCurrentTenant();

        return $settings->isConnected() && $settings->isAutoPushEnabled();
    }
}
