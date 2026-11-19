# Queuebert

A live control-panel bar for Craft CMS 6 that surfaces running and queued jobs — across **Craft's queues** and **any other Laravel queue** — and shows up **only while there's queue activity**.

When jobs are running or waiting, a slim bar slides in at the top of the control panel with the current job's description, a progress indicator, and running/waiting/scheduled/failed counts. When the queues go quiet, the bar disappears.

> Using Craft 5? Install Queuebert 5.x (`composer require justinholtweb/craft-queuebert:^5.0`); its docs are on the `main` branch.

## Requirements

- Craft CMS 6.0.0-beta.1 or later
- PHP 8.5 or later

## Installation

```bash
composer require justinholtweb/craft-queuebert
php artisan craft:plugin:install queuebert
```

Upgrading from Craft 5? Update to Queuebert 5.0.2 first, then upgrade Craft. Your settings carry over unchanged.
See [UPGRADING.md](UPGRADING.md) for the full guide, including how Craft 5 custom queue components map to Craft 6.

## How it works

Queuebert adds a small script to every control-panel page. It polls
`/admin/queuebert/activity` (signed-in control-panel users only) and reads
two sources:

1. **Craft's queues.** Craft records progress for every job on the queues named
   in its `trackedQueueNames` setting (`config/craft/general.php`, default
   `['default']`). These give the bar its headline: the running job's
   description and progress.
2. **Other Laravel queues.** Queue names that Craft doesn't track — your own
   Laravel jobs dispatched with `->onQueue('imports')`, for example. Laravel's
   drivers only report sizes for these, so they contribute running, waiting,
   scheduled and failed *counts*.

The bar only shows the kind of information Craft's own queue indicator already
exposes: job counts, the current job's description, and its progress.

## Settings

| Setting | Default | Description |
| --- | --- | --- |
| **Poll interval** | `3` | Seconds between activity checks while a CP tab is open (1–60). The poller backs off when idle or when the tab is hidden. |
| **Count waiting jobs as activity** | `on` | Show the bar when jobs are queued but not yet running. Delayed jobs that aren't due yet never show the bar on their own. |
| **Surface failed jobs** | `on` | Show the bar (in an alert color) when there are failed jobs. |
| **Discover queues automatically** | `on` | Watch every queue name that has jobs (or failures) on a `database`-driver connection. |
| **Additional queues** | — | Extra queues to watch, one per line: `imports` (default connection) or `redis:emails` (`connection:queue`). Redis, SQS and Beanstalkd queues can't be discovered, so list them here. |
| **Inspect limit** | `100` | Max Craft jobs inspected when building the summary. |

All settings can also be set in `config/craft/queuebert.php`, where
`watchQueues` may be an array:

```php
return [
    'pollInterval' => 5,
    'watchQueues' => ['imports', 'redis:emails'],
];
```

## Custom queues

Craft 6 runs on Laravel's queue, so a "custom queue" is just a queue name (and
optionally a connection) in `config/queue.php`:

```php
// Dispatch onto a separate queue…
ImportProducts::dispatch($feed)->onQueue('imports');

// …and run a worker for it alongside Craft's:
// php artisan queue:work --queue=default,imports
```

On the `database` driver Queuebert finds `imports` on its own. For other
drivers, add it under **Additional queues**. If you'd rather have Craft track
the queue itself (with descriptions and progress), add it to
`trackedQueueNames` instead. Queuebert then reports it under Craft's queue.

If a watched queue can't be read (say, Redis is down), Queuebert skips it and
logs a warning at most once every 10 minutes.

## Troubleshooting

**The bar never appears.** It only shows while there's activity. Check that a
job is actually running or waiting, that **Count waiting jobs as activity** is
on if jobs are only waiting, and that a worker is running
(`php artisan queue:work`). Delayed jobs that aren't due yet never show the
bar on their own.

**A queue isn't counted.** Queuebert can only count queues on drivers that
report sizes. `sync`, `null`, `deferred`, `background` and `failover`
connections are skipped. On Redis, SQS or Beanstalkd, list the queue under
**Additional queues**. Discovery only covers the `database` driver.

**A queue shows counts but no job description or progress.** Only queues in
Craft's `trackedQueueNames` carry descriptions and progress. Add the queue name
there if you want them.

**"Queuebert could not read queue …" in the logs.** A watched queue's
connection failed (Redis down, missing credentials). Queuebert skips it and
logs a warning at most once every 10 minutes per queue.

**The bar stopped updating after I signed out.** The poller stops when the
endpoint returns 401 or 403. Reload the page after signing back in.
