<?php

declare(strict_types=1);

namespace JustinHoltWeb\Queuebert\Http\Controllers;

use Illuminate\Http\JsonResponse;
use JustinHoltWeb\Queuebert\Queue\QueueInspector;
use JustinHoltWeb\Queuebert\Settings;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

/**
 * Serves the live queue-activity summary polled by the control-panel bar.
 *
 * Any signed-in control-panel user may read it (the cp route group requires
 * `accessCp`); it exposes job counts, descriptions and progress — the same
 * information Craft's own queue indicator shows.
 */
class ActivityController
{
    public function __invoke(QueueInspector $inspector, Settings $settings): JsonResponse
    {
        return response()->json([
            ...$inspector->activity(),
            'pollInterval' => $settings->pollInterval,
            'manageUrl' => cp_url('utilities/queue-manager'),
            'labels' => [
                'title' => t('Queue activity', category: 'queuebert'),
                'manage' => t('Manage', category: 'queuebert'),
                'running' => t('running', category: 'queuebert'),
                'waiting' => t('waiting', category: 'queuebert'),
                'scheduled' => t('scheduled', category: 'queuebert'),
                'failed' => t('failed', category: 'queuebert'),
            ],
        ])->header('Cache-Control', 'no-store');
    }
}
