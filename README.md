# Queuebert

A live control-panel bar for Craft CMS 5 that surfaces running and queued jobs — across Craft's **default queue** and **any custom queue runners** — and shows up **only while there's queue activity**.

When jobs are running or waiting, a slim bar slides in at the top of the control panel with the current job's description, a progress indicator, and running/waiting/failed counts. When the queues go quiet, the bar disappears.

## Requirements

- Craft CMS 5.4.0 or later
- PHP 8.2 or later

## Installation

Install with Composer and enable in the control panel (or via the CLI):

```bash
composer require justinholtweb/craft-queuebert
php craft plugin/install queuebert
```

## How it works

Queuebert injects a lightweight poller into every control-panel page. It calls
`queuebert/queue/info`, which asks Craft for the primary queue plus every
application component implementing `craft\queue\QueueInterface` (custom queue
runners are conventionally registered as app components in `config/app.php`),
and aggregates their job info.

The bar only shows the same information Craft's own queue HUD already exposes:
job counts, the current job's description, and its progress.

## Settings

| Setting | Default | Description |
| --- | --- | --- |
| **Poll interval** | `3` | Seconds between activity checks while a CP tab is open (1–60). The poller automatically backs off when idle or when the tab is hidden. |
| **Count waiting jobs as activity** | `on` | Show the bar when jobs are queued but not yet running. Delayed jobs that aren't due yet never show the bar on their own. |
| **Surface failed jobs** | `on` | Show the bar (in an alert color) when there are failed jobs. |
| **Inspect limit** | `100` | Max jobs inspected per queue when building the summary. |

## Custom queues

Any component that implements `craft\queue\QueueInterface` is picked up
automatically. For example, a second database-backed queue registered in
`config/app.php`:

```php
return [
    'components' => [
        'importQueue' => [
            'class' => \craft\queue\Queue::class,
            'db' => 'db',
            'channel' => 'import',
            'tableName' => '{{%queue}}',
            'mutex' => \craft\mutex\Mutex::class,
        ],
    ],
];
```

...appears in the bar's totals alongside the default queue.
