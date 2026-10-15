<?php

namespace justinholtweb\queuebert\services;

/**
 * Pure, framework-agnostic aggregation of queue job info into the activity
 * summary rendered by the control-panel bar.
 *
 * Kept free of any Craft/Yii dependencies so it can be unit-tested without
 * bootstrapping the application. {@see Queues} is responsible for discovering
 * queues, reading their job info, and translating strings before handing the
 * normalized data here.
 */
final class QueueActivity
{
    // Job statuses, per craft\queue\QueueInterface::getJobInfo().
    public const STATUS_WAITING = 1;
    public const STATUS_RESERVED = 2; // running
    public const STATUS_DONE = 3;
    public const STATUS_FAILED = 4;

    /**
     * Aggregates per-queue job info into the summary shape the bar consumes.
     *
     * @param list<array{handle: string, name: string, jobs: list<array{status?: int, delay?: int, description?: string, progress?: int, progressLabel?: string}>}> $queues
     *     Discovered queues, in the order they should be considered. The first
     *     running job encountered becomes the "current" headline job.
     * @param bool $countWaiting Whether waiting jobs make the queues "active".
     *     Delayed jobs (waiting with a positive `delay`) are counted separately
     *     as scheduled and never make the queues active on their own.
     * @param bool $showFailed Whether failed jobs make the queues "active" and
     *     keep an otherwise-idle queue in the per-queue breakdown.
     * @param string $runningFallback Description to use for the current job when
     *     it reports none.
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
    public static function summarize(
        array $queues,
        bool $countWaiting = true,
        bool $showFailed = true,
        string $runningFallback = 'Running job',
    ): array {
        $totalRunning = 0;
        $totalWaiting = 0;
        $totalScheduled = 0;
        $totalFailed = 0;
        $queueSummaries = [];
        $current = null;

        foreach ($queues as $queue) {
            $running = 0;
            $waiting = 0;
            $scheduled = 0;
            $failed = 0;

            foreach ($queue['jobs'] as $job) {
                switch ((int)($job['status'] ?? 0)) {
                    case self::STATUS_RESERVED:
                        $running++;
                        // The first running job we see is the headline.
                        if ($current === null) {
                            $description = (string)($job['description'] ?? '');
                            $current = [
                                'description' => $description !== '' ? $description : $runningFallback,
                                'progress' => self::clampProgress($job['progress'] ?? 0),
                                'progressLabel' => (string)($job['progressLabel'] ?? ''),
                                'queue' => $queue['name'],
                            ];
                        }
                        break;
                    case self::STATUS_WAITING:
                        // Craft reports delayed jobs as waiting, with the
                        // seconds remaining in `delay`. They aren't due yet.
                        if ((int)($job['delay'] ?? 0) > 0) {
                            $scheduled++;
                            break;
                        }
                        $waiting++;
                        break;
                    case self::STATUS_FAILED:
                        $failed++;
                        break;
                }
            }

            $totalRunning += $running;
            $totalWaiting += $waiting;
            $totalScheduled += $scheduled;
            $totalFailed += $failed;

            // Only report queues with something worth showing.
            if ($running || $waiting || ($showFailed && $failed)) {
                $queueSummaries[] = [
                    'handle' => $queue['handle'],
                    'name' => $queue['name'],
                    'running' => $running,
                    'waiting' => $waiting,
                    'scheduled' => $scheduled,
                    'failed' => $failed,
                ];
            }
        }

        $active = $totalRunning > 0
            || ($countWaiting && $totalWaiting > 0)
            || ($showFailed && $totalFailed > 0);

        return [
            'active' => $active,
            'totalRunning' => $totalRunning,
            'totalWaiting' => $totalWaiting,
            'totalScheduled' => $totalScheduled,
            'totalFailed' => $totalFailed,
            'current' => $current,
            'queues' => $queueSummaries,
        ];
    }

    /**
     * Clamps a raw progress value to the 0–100 range.
     */
    private static function clampProgress(mixed $progress): int
    {
        return max(0, min(100, (int)$progress));
    }
}
