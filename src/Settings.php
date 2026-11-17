<?php

declare(strict_types=1);

namespace JustinHoltWeb\Queuebert;

use CraftCms\Cms\Plugin\PluginSettings;

use function CraftCms\Cms\t;

class Settings extends PluginSettings
{
    /** Seconds between activity polls while a control-panel tab is open. */
    public int $pollInterval = 3;

    /** Treat waiting jobs as activity (show the bar before a worker picks them up). */
    public bool $countWaiting = true;

    /** Surface failed jobs in the bar so they aren't missed. */
    public bool $showFailed = true;

    /** Max Craft job-progress records to inspect when building the summary. */
    public int $inspectLimit = 100;

    /** Find queue names on `database`-driver connections automatically. */
    public bool $autoDiscover = true;

    /**
     * Extra Laravel queues to watch, one per line (or an array in the config
     * file): `queue` on the default connection, or `connection:queue`.
     *
     * Nullable because Laravel's ConvertEmptyStringsToNull middleware turns a
     * blank field into null on save.
     *
     * @var string|list<string>|null
     */
    public string|array|null $watchQueues = null;

    public function getRules(): array
    {
        return [
            'pollInterval' => ['required', 'integer', 'min:1', 'max:60'],
            'inspectLimit' => ['required', 'integer', 'min:1'],
            'countWaiting' => ['boolean'],
            'showFailed' => ['boolean'],
            'autoDiscover' => ['boolean'],
            'watchQueues' => ['nullable'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'pollInterval' => t('Poll interval', category: 'queuebert'),
            'inspectLimit' => t('Inspect limit', category: 'queuebert'),
            'watchQueues' => t('Additional queues', category: 'queuebert'),
        ];
    }

    /**
     * `watchQueues` as `[connection, queue]` pairs.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function watchedQueuePairs(string $defaultConnection): array
    {
        $lines = is_array($this->watchQueues) ? $this->watchQueues : preg_split('/[\r\n,]+/', (string) $this->watchQueues);
        $pairs = [];

        foreach ($lines ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            [$connection, $queue] = str_contains($line, ':') ? array_map('trim', explode(':', $line, 2)) : [$defaultConnection, $line];
            if ($connection !== '' && $queue !== '') {
                $pairs[] = [$connection, $queue];
            }
        }

        return $pairs;
    }
}
