<?php

declare(strict_types=1);

use App\Controller\HomeController;
use App\I18n\Locale;
use Slim\App;

return function (App $app): void {
    foreach (Locale::cases() as $locale) {
        $app->get($locale->homePath(), [HomeController::class, 'index']);
    }
};
