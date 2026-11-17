<?php

declare(strict_types=1);

namespace JustinHoltWeb\Queuebert\Queue;

/**
 * Pure, framework-agnostic aggregation of queue activity into the summary the
 * control-panel bar renders.
 *
 * Kept free of Craft and Laravel so it can be unit-tested without booting an
 * application. {@see QueueInspector} reads Craft's job-progress records and the
 * Laravel queue connections, normalizes them, and hands the result here.
 *
 * A queue can describe its activity two ways, and may use both:
 *
 * - `jobs`: individual job rows (Craft's tracked queues), which carry the
 *   description and progress the bar headlines.
 * - `counts`: bare totals (other Laravel queues, whose drivers only report
 *   sizes). A running count with no row still makes the queue active, with a
 *   generic headline.
 */
final class QueueActivity
{
    // Mirrors CraftCms\Cms\Queue\Enums\JobStatus.
    public const int STATUS_WAITING = 1;

    public const int STATUS_RESERVED = 2; // running

    public const int STATUS_DONE = 3;

    public const int STATUS_FAILED = 4;

    public const int STATUS_DELAYED = 5;

    public const int STATUS_CANCELLED = 6;

    /**
     * @param  list<array{
     *     handle: string,
     *     name: string,
     *     jobs?: list<array{status?: int, delay?: int|null, description?: string, progress?: int, progressLabel?: string}>,
     *     counts?: array<string, int>,
     * }>  $queues  In the order they should be considered; the first running job
     *     found becomes the headline.
     * @param  bool  $countWaiting  Whether waiting jobs make the queues active.
     *                              Scheduled (delayed) jobs never do on their own.
     * @param  bool  $showFailed  Whether failed jobs make the queues active and
     *                            keep an otherwise idle queue in the breakdown.
     * @param  string  $runningFallback  Headline for a running job that has no
     *                                   description (or no row at all).
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
        $totals = ['running' => 0, 'waiting' => 0, 'scheduled' => 0, 'failed' => 0];
        $queueSummaries = [];
        $current = null;

        foreach ($queues as $queue) {
            $counts = ['running' => 0, 'waiting' => 0, 'scheduled' => 0, 'failed' => 0];

            foreach ($queue['jobs'] ?? [] as $job) {
                switch ((int) ($job['status'] ?? 0)) {
                    case self::STATUS_RESERVED:
                        $counts['running']++;
                        if ($current === null) {
                            $description = (string) ($job['description'] ?? '');
                            $current = [
                                'description' => $description !== '' ? $description : $runningFallback,
                                'progress' => self::clampProgress($job['progress'] ?? 0),
                                'progressLabel' => (string) ($job['progressLabel'] ?? ''),
                                'queue' => $queue['name'],
                            ];
                        }
                        break;
                    case self::STATUS_DELAYED:
                        $counts['scheduled']++;
                        break;
                    case self::STATUS_WAITING:
                        // A waiting row with a future delay isn't due yet.
                        if ((int) ($job['delay'] ?? 0) > 0) {
                            $counts['scheduled']++;
                            break;
                        }
                        $counts['waiting']++;
                        break;
                    case self::STATUS_FAILED:
                        $counts['failed']++;
                        break;
                }
            }

            foreach ($queue['counts'] ?? [] as $key => $value) {
                if (isset($counts[$key])) {
                    $counts[$key] += max(0, (int) $value);
                }
            }

            if ($current === null && $counts['running'] > 0) {
                $current = [
                    'description' => $runningFallback,
                    'progress' => 0,
                    'progressLabel' => '',
                    'queue' => $queue['name'],
                ];
            }

            foreach ($counts as $key => $value) {
                $totals[$key] += $value;
            }

            if ($counts['running'] || $counts['waiting'] || ($showFailed && $counts['failed'])) {
                $queueSummaries[] = [
                    'handle' => $queue['handle'],
                    'name' => $queue['name'],
                    ...$counts,
                ];
            }
        }

        return [
            'active' => $totals['running'] > 0
                || ($countWaiting && $totals['waiting'] > 0)
                || ($showFailed && $totals['failed'] > 0),
            'totalRunning' => $totals['running'],
            'totalWaiting' => $totals['waiting'],
            'totalScheduled' => $totals['scheduled'],
            'totalFailed' => $totals['failed'],
            'current' => $current,
            'queues' => $queueSummaries,
        ];
    }

    private static function clampProgress(mixed $progress): int
    {
        return max(0, min(100, (int) $progress));
    }
}
