<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Test Case bindings
 |--------------------------------------------------------------------------
 |
 | The activity aggregation (QueueActivity) is framework-agnostic and needs no
 | Craft bootstrap, so these unit tests run on plain PHPUnit via Pest. Queue
 | discovery and string translation live in the Craft-coupled Queues service
 | and are covered by live integration testing in the plugin-testing harness.
 |
 */

uses()->group('unit')->in('Unit');

/*
 |--------------------------------------------------------------------------
 | Helpers
 |--------------------------------------------------------------------------
 */

use justinholtweb\queuebert\services\QueueActivity;

/**
 * Build a single job-info row, defaulting to a waiting job.
 *
 * @return array{status: int, description: string, progress: int, progressLabel: string}
 */
function job(
    int $status = QueueActivity::STATUS_WAITING,
    string $description = '',
    int $progress = 0,
    string $progressLabel = '',
): array {
    return compact('status', 'description', 'progress', 'progressLabel');
}

/**
 * Build a queue entry for QueueActivity::summarize().
 *
 * @param list<array> $jobs
 * @return array{handle: string, name: string, jobs: list<array>}
 */
function queue(string $handle, string $name, array $jobs): array
{
    return compact('handle', 'name', 'jobs');
}
