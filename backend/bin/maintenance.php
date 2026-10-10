<?php
declare(strict_types=1);

/** Cron entry point: expires overdue invitations and requests. Example: */
/* * * * * * php /path/to/backend/bin/maintenance.php */

use TaskBuddy\Services\InvitationService;
use TaskBuddy\Support\App;

require __DIR__ . '/../vendor/autoload.php';
App::boot();
echo 'Expired/updated: ' . (new InvitationService())->expireStale() . "\n";
