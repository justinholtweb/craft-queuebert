<?php

declare(strict_types=1);

use JustinHoltWeb\Queuebert\Settings;

it('parses watchQueues lines into connection/queue pairs', function () {
    $settings = new Settings;
    $settings->watchQueues = "imports\n redis:emails \n\n,sqs:  jobs";

    expect($settings->watchedQueuePairs('database'))->toBe([
        ['database', 'imports'],
        ['redis', 'emails'],
        ['sqs', 'jobs'],
    ]);
});

it('accepts watchQueues as an array from the config file', function () {
    $settings = new Settings;
    $settings->watchQueues = ['imports', 'redis:emails', '', ':nope', 'nope:'];

    expect($settings->watchedQueuePairs('database'))->toBe([
        ['database', 'imports'],
        ['redis', 'emails'],
    ]);
});

it('treats a blank (null) watchQueues as no extra queues', function () {
    $settings = new Settings;
    $settings->watchQueues = null;

    expect($settings->watchedQueuePairs('database'))->toBe([]);
});
