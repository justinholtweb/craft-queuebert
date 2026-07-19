<?php

declare(strict_types=1);

use justinholtweb\queuebert\services\QueueActivity;

const WAITING = QueueActivity::STATUS_WAITING;
const RESERVED = QueueActivity::STATUS_RESERVED;
const DONE = QueueActivity::STATUS_DONE;
const FAILED = QueueActivity::STATUS_FAILED;

it('reports no activity for empty input', function() {
    $summary = QueueActivity::summarize([]);

    expect($summary['active'])->toBeFalse()
        ->and($summary['totalRunning'])->toBe(0)
        ->and($summary['totalWaiting'])->toBe(0)
        ->and($summary['totalFailed'])->toBe(0)
        ->and($summary['current'])->toBeNull()
        ->and($summary['queues'])->toBe([]);
});

it('counts running, waiting and failed jobs', function() {
    $summary = QueueActivity::summarize([
        queue('queue', 'Default queue', [
            job(RESERVED, 'Rebuilding index', 40),
            job(WAITING),
            job(WAITING),
            job(FAILED),
            job(DONE), // ignored
        ]),
    ]);

    expect($summary['active'])->toBeTrue()
        ->and($summary['totalRunning'])->toBe(1)
        ->and($summary['totalWaiting'])->toBe(2)
        ->and($summary['totalFailed'])->toBe(1)
        ->and($summary['queues'])->toHaveCount(1)
        ->and($summary['queues'][0])->toMatchArray([
            'handle' => 'queue',
            'name' => 'Default queue',
            'running' => 1,
            'waiting' => 2,
            'failed' => 1,
        ]);
});

it('exposes the first running job as the current headline', function() {
    $summary = QueueActivity::summarize([
        queue('queue', 'Default queue', [
            job(RESERVED, 'First job', 25, 'First job (1/4)'),
            job(RESERVED, 'Second job', 90),
        ]),
    ]);

    expect($summary['current'])->toBe([
        'description' => 'First job',
        'progress' => 25,
        'progressLabel' => 'First job (1/4)',
        'queue' => 'Default queue',
    ]);
});

it('falls back to a default description for a nameless running job', function() {
    $summary = QueueActivity::summarize(
        [queue('queue', 'Default queue', [job(RESERVED, '', 10)])],
        runningFallback: 'Running job',
    );

    expect($summary['current']['description'])->toBe('Running job');
});

it('clamps progress into the 0–100 range', function(int $raw, int $expected) {
    $summary = QueueActivity::summarize([
        queue('queue', 'Default queue', [job(RESERVED, 'Job', $raw)]),
    ]);

    expect($summary['current']['progress'])->toBe($expected);
})->with([
    'negative' => [-20, 0],
    'over 100' => [140, 100],
    'in range' => [55, 55],
]);

it('treats waiting jobs as inactive when countWaiting is off', function() {
    $queues = [queue('queue', 'Default queue', [job(WAITING), job(WAITING)])];

    expect(QueueActivity::summarize($queues, countWaiting: true)['active'])->toBeTrue()
        ->and(QueueActivity::summarize($queues, countWaiting: false)['active'])->toBeFalse();
});

it('still counts running jobs when countWaiting is off', function() {
    $summary = QueueActivity::summarize(
        [queue('queue', 'Default queue', [job(RESERVED, 'Job', 5), job(WAITING)])],
        countWaiting: false,
    );

    expect($summary['active'])->toBeTrue()
        ->and($summary['totalRunning'])->toBe(1)
        ->and($summary['totalWaiting'])->toBe(1);
});

it('excludes an only-failed queue and stays inactive when showFailed is off', function() {
    $queues = [queue('queue', 'Default queue', [job(FAILED), job(DONE)])];

    $shown = QueueActivity::summarize($queues, showFailed: true);
    $hidden = QueueActivity::summarize($queues, showFailed: false);

    expect($shown['active'])->toBeTrue()
        ->and($shown['queues'])->toHaveCount(1)
        ->and($hidden['active'])->toBeFalse()
        ->and($hidden['totalFailed'])->toBe(1) // still counted, just not "active"
        ->and($hidden['queues'])->toBe([]); // idle queue dropped from the breakdown
});

it('omits queues with no reportable jobs from the breakdown', function() {
    $summary = QueueActivity::summarize([
        queue('queue', 'Default queue', [job(WAITING)]),
        queue('idle', 'Idle queue', [job(DONE), job(DONE)]),
    ]);

    expect($summary['queues'])->toHaveCount(1)
        ->and($summary['queues'][0]['handle'])->toBe('queue');
});

it('aggregates across multiple queues and picks the first running job in order', function() {
    $summary = QueueActivity::summarize([
        queue('queue', 'Default queue', [job(WAITING), job(WAITING)]),
        queue('importQueue', 'importQueue', [job(RESERVED, 'CSV import', 60), job(WAITING)]),
    ]);

    expect($summary['totalRunning'])->toBe(1)
        ->and($summary['totalWaiting'])->toBe(3)
        ->and($summary['queues'])->toHaveCount(2)
        ->and($summary['current']['description'])->toBe('CSV import')
        ->and($summary['current']['queue'])->toBe('importQueue');
});

it('defaults a missing status to no-op rather than miscounting', function() {
    $summary = QueueActivity::summarize([
        ['handle' => 'queue', 'name' => 'Default queue', 'jobs' => [
            ['progress' => 10], // no status key
            job(WAITING),
        ]],
    ]);

    expect($summary['totalRunning'])->toBe(0)
        ->and($summary['totalWaiting'])->toBe(1)
        ->and($summary['totalFailed'])->toBe(0);
});
