# Upgrading Queuebert from Craft 5 to Craft 6

Queuebert 6 is a native Craft 6 plugin. It runs on Laravel's queue and has no
dependency on the Yii 2 adapter. Your settings carry over, there's no data to
migrate, and for most sites the upgrade is two Composer commands.

> **Beta.** Queuebert 6 is in beta, like Craft 6 itself. Test the upgrade on a copy of your site
> before relying on it in production. Queuebert 5 remains the stable release for Craft 5.

## Before you start

| | Queuebert 5.x | Queuebert 6.x |
| --- | --- | --- |
| Status | Stable | Beta |
| Craft CMS | 5.4.0+ | 6.0.0+ |
| PHP | 8.2+ | 8.5+ |
| Install command | `php craft plugin/install queuebert` | `php artisan craft:plugin:install queuebert` |

1. **Update to Queuebert 5.0.2 first**, while the site is still on Craft 5:

   ```bash
   composer require justinholtweb/craft-queuebert:^5.0.2
   php craft up
   ```

   Queuebert 6 refuses to install over anything older than 5.0.2.

2. **Check how your site defines extra queues.** If `config/app.php`
   registers a second queue component (an `importQueue`, for example), read
   [Custom queues](#custom-queues) below before you upgrade.

## Upgrade

Follow Craft's own [Craft 6 upgrade guide](https://craftcms.com/docs/6.x/upgrade.html),
and require Queuebert 6 at the same time as Craft:

```bash
composer require "craftcms/cms:^6.0@beta" "justinholtweb/craft-queuebert:^6.0@beta" -W
php artisan craft:up
```

Drop the `@beta` flags once Craft 6 and Queuebert 6 have stable releases.

That's it. The bar appears the next time a job runs or waits.

## What carries over

- **Settings.** Queuebert's settings live in project config under the same
  keys, so the poll interval, waiting/failed toggles and inspect limit carry
  over unchanged.
- **Config file overrides.** If you had `config/queuebert.php`, move it
  alongside Craft's other config files at `config/craft/queuebert.php`. The
  keys are the same.

## What changed

### Custom queues

Craft 5 queues were Yii application components, and Queuebert 5 read every
component implementing `craft\queue\QueueInterface`. Craft 6 has no
application components: its queue is Laravel's queue, and a "custom queue" is
just a queue name, optionally on its own connection in `config/queue.php`.

A Craft 5 component like this:

```php
// config/app.php (Craft 5)
'components' => [
    'importQueue' => [
        'class' => \craft\queue\Queue::class,
        'channel' => 'import',
    ],
],
```

becomes a queue name you dispatch to and run a worker for:

```php
ImportProducts::dispatch($feed)->onQueue('imports');
```

```bash
php artisan queue:work --queue=default,imports
```

Queuebert then picks the queue up in one of three ways:

| You want | Do this |
| --- | --- |
| Counts for a queue on the `database` driver | Nothing. **Discover queues automatically** (on by default) finds it. |
| Counts for a Redis, SQS or Beanstalkd queue | Add it to **Additional queues**: `imports` or `redis:imports`. |
| Descriptions and progress, like Craft's own queue | Add it to Craft's `trackedQueueNames` in `config/craft/general.php`. |

### New settings

- **Discover queues automatically** (`autoDiscover`, on): watch every queue
  name that has jobs or failures on a `database`-driver connection.
- **Additional queues** (`watchQueues`, empty): queues to watch that can't be
  discovered, one per line. In the config file it can be an array.

### Counts

The bar now shows four counts: running, waiting, **scheduled** (delayed jobs
that aren't due yet) and failed. Scheduled jobs never show the bar on their
own. Failures on non-Craft queues come from Laravel's failed-jobs table.

**Inspect limit** now caps the Craft jobs Queuebert inspects. Laravel queues
are counted by their driver, so the limit doesn't apply to them.

### The activity endpoint

The bar polls `admin/queuebert/activity` (your `cpTrigger`, then
`queuebert/activity`) instead of `actions/queuebert/queue/info`. It's
still limited to signed-in users with control-panel access. You only need to
care if a firewall or proxy rule allow-lists the old path.

### Translations

Plugin translations moved from `src/translations` to `lang/en/queuebert.php`.
If you overrode Queuebert's strings, re-check your overrides against the new
file. A few labels were added: "scheduled", "Craft queue", "Queues",
"Discover queues automatically" and "Additional queues".

## Rolling back

Queuebert 6 doesn't change your database. To go back, restore your Craft 5
site and database from backup and require `justinholtweb/craft-queuebert:^5.0.2`.
