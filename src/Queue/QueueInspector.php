<?php

declare(strict_types=1);

namespace JustinHoltWeb\Queuebert\Queue;

use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Queue\Enums\JobStatus;
use CraftCms\Cms\Queue\JobProgress;
use CraftCms\Cms\Queue\Models\JobProgress as JobProgressRecord;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Queue\Failed\CountableFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Log;
use JustinHoltWeb\Queuebert\Settings;
use Throwable;

use function CraftCms\Cms\t;

/**
 * Reads live queue activity from two places and hands it to {@see QueueActivity}:
 *
 * 1. Craft's job-progress records, which cover every queue named in the
 *    `trackedQueueNames` config setting and carry descriptions and progress.
 * 2. Every other Laravel queue Queuebert can see: queue names discovered on
 *    `database`-driver connections, plus anything listed in the `watchQueues`
 *    setting. Laravel drivers only report sizes for these, so they contribute
 *    counts rather than job rows.
 */
class QueueInspector
{
    /** Drivers that have no inspectable backlog. */
    private const array UNCOUNTABLE_DRIVERS = ['sync', 'null', 'deferred', 'background', 'failover'];

    public function __construct(
        private readonly Settings $settings,
        private readonly GeneralConfig $generalConfig,
        private readonly JobProgress $jobProgress,
        private readonly QueueFactory $queues,
        private readonly Config $config,
        private readonly DatabaseManager $db,
        private readonly FailedJobProviderInterface $failer,
        private readonly Cache $cache,
    ) {}

    /**
     * @return array{
     *     active: bool,
     *     totalRunning: int,
     *     totalWaiting: int,
     *     totalScheduled: int,
     *     totalFailed: int,
     *     current: array{description: string, progress: int, progressLabel: string, queue: string}|null,
     *     queues: list<array{handle: string, name: string, running: int, waiting: int, scheduled: int, failed: int}>
     * }
     */
    public function activity(): array
    {
        return QueueActivity::summarize(
            [$this->craftQueue(), ...$this->laravelQueues()],
            $this->settings->countWaiting,
            $this->settings->showFailed,
            t('Running job', category: 'queuebert'),
        );
    }

    /**
     * Craft's tracked queues, as one entry built from job-progress records.
     *
     * @return array{handle: string, name: string, jobs: list<array{status: int, delay: int|null, description: string, progress: int, progressLabel: string}>}
     */
    public function craftQueue(): array
    {
        $jobs = [];

        try {
            $records = $this->jobProgress->jobsQuery()->limit(max(1, $this->settings->inspectLimit))->get();

            // Core's JobProgress model declares no @property types, so read the
            // columns explicitly rather than through magic properties.
            foreach ($records as $record) {
                /** @var JobProgressRecord $record */
                $status = $record->getAttribute('status');
                $description = (string) $record->getAttribute('description');
                $progressLabel = (string) $record->getAttribute('progressLabel');
                $delay = $record->getAttribute('delay');

                $jobs[] = [
                    'status' => $status instanceof JobStatus ? $status->value : (int) $status,
                    'delay' => $delay === null ? null : (int) $delay,
                    'description' => $description !== '' ? t($description) : '',
                    'progress' => (int) $record->getAttribute('progress'),
                    'progressLabel' => $progressLabel !== '' ? t($progressLabel) : '',
                ];
            }
        } catch (Throwable $e) {
            $this->warnOnce('craft', 'Queuebert could not read Craft job progress: '.$e->getMessage());
        }

        return [
            'handle' => 'craft',
            'name' => t('Craft queue', category: 'queuebert'),
            'jobs' => $jobs,
        ];
    }

    /**
     * Every untracked Laravel queue with its sizes.
     *
     * @return list<array{handle: string, name: string, counts: array{running: int, waiting: int, scheduled: int, failed: int}}>
     */
    public function laravelQueues(): array
    {
        $entries = [];

        foreach ($this->watchedQueues() as [$connection, $queueName]) {
            try {
                $queue = $this->queues->connection($connection);
                $counts = [
                    'running' => (int) $queue->reservedSize($queueName),
                    'waiting' => (int) $queue->pendingSize($queueName),
                    'scheduled' => (int) $queue->delayedSize($queueName),
                    'failed' => $this->failedCount($connection, $queueName),
                ];
            } catch (Throwable $e) {
                $this->warnOnce("read:$connection:$queueName", "Queuebert could not read queue \"$connection:$queueName\": ".$e->getMessage());

                continue;
            }

            $entries[] = [
                'handle' => "$connection:$queueName",
                'name' => $connection === $this->defaultConnection() ? $queueName : "$queueName ($connection)",
                'counts' => $counts,
            ];
        }

        return $entries;
    }

    /**
     * The `[connection, queue]` pairs to inspect, excluding Craft's tracked
     * queues (which {@see craftQueue()} already covers).
     *
     * @return list<array{0: string, 1: string}>
     */
    public function watchedQueues(): array
    {
        $tracked = $this->generalConfig->trackedQueueNames;
        $pairs = [];

        $add = function (string $connection, string $queue) use (&$pairs, $tracked): void {
            if ($queue === '' || in_array($queue, $tracked, true) || ! $this->isCountable($connection)) {
                return;
            }
            $pairs["$connection:$queue"] = [$connection, $queue];
        };

        if ($this->settings->autoDiscover) {
            foreach ($this->connections() as $name => $connection) {
                if (($connection['driver'] ?? null) !== 'database') {
                    continue;
                }
                foreach ($this->discoverDatabaseQueues($name, $connection) as $queue) {
                    $add($name, $queue);
                }
            }
        }

        foreach ($this->settings->watchedQueuePairs($this->defaultConnection()) as [$connection, $queue]) {
            $add($connection, $queue);
        }

        return array_values($pairs);
    }

    /**
     * Distinct queue names with jobs (or failures) on a database connection,
     * plus the connection's configured default queue.
     *
     * @param  array<string, mixed>  $connection
     * @return list<string>
     */
    private function discoverDatabaseQueues(string $name, array $connection): array
    {
        $queues = [(string) ($connection['queue'] ?? 'default')];

        try {
            $db = $this->db->connection($connection['connection'] ?? null);
            $queues = [...$queues, ...$db->table($connection['table'] ?? 'jobs')->distinct()->pluck('queue')->all()];

            $failedTable = $this->config->get('queue.failed.table');
            if (is_string($failedTable) && $db->getSchemaBuilder()->hasTable($failedTable)) {
                $queues = [...$queues, ...$db->table($failedTable)->where('connection', $name)->distinct()->pluck('queue')->all()];
            }
        } catch (Throwable $e) {
            $this->warnOnce("discover:$name", "Queuebert could not discover queues on \"$name\": ".$e->getMessage());
        }

        return array_values(array_unique(array_map('strval', $queues)));
    }

    /**
     * Every open CP tab polls every few seconds, so a misconfigured queue would
     * otherwise log the same warning constantly. Log it at most every 10 minutes.
     */
    private function warnOnce(string $key, string $message): void
    {
        try {
            if (! $this->cache->add("queuebert:warned:$key", true, 600)) {
                return;
            }
        } catch (Throwable) {
            // No cache: fall through and log.
        }

        Log::warning($message);
    }

    private function failedCount(string $connection, string $queue): int
    {
        if (! $this->failer instanceof CountableFailedJobProvider) {
            return 0;
        }

        try {
            return (int) $this->failer->count($connection, $queue);
        } catch (Throwable) {
            return 0;
        }
    }

    private function isCountable(string $connection): bool
    {
        $driver = $this->connections()[$connection]['driver'] ?? null;

        return is_string($driver) && ! in_array($driver, self::UNCOUNTABLE_DRIVERS, true);
    }

    /** @return array<string, array<string, mixed>> */
    private function connections(): array
    {
        return (array) $this->config->get('queue.connections', []);
    }

    private function defaultConnection(): string
    {
        return (string) $this->config->get('queue.default', 'sync');
    }
}
