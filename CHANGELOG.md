# Release Notes for Queuebert

## 6.0.0-beta.1 - 2026-11-18

> {note} Queuebert 6 requires Craft CMS 6 and PHP 8.5. Update to Queuebert 5.0.2 before upgrading Craft, and see [the upgrade guide](https://github.com/justinholtweb/craft-queuebert/blob/6.0.0/UPGRADING.md) if your site registers custom queue components.

### Changed

- Rebuilt as a native Craft 6 plugin. It has no dependency on the Yii 2 adapter.
- Queue activity now comes from Craft's job-progress records (every queue in `trackedQueueNames`) plus Laravel queue sizes for every other queue, replacing Craft 5's queue components.
- The activity endpoint moved to `admin/queuebert/activity`.
- The control-panel bar is now dependency-free JavaScript that mounts above Craft 6's header bar and stays put across control-panel page visits.

### Added

- "Discover queues automatically" setting: watches every queue name on a `database`-driver connection.
- "Additional queues" setting (`watchQueues`): watch Redis, SQS or Beanstalkd queues by name.
- Failed jobs on non-Craft queues are counted from Laravel's failed-jobs store.

### Fixed

- Unreadable queues log one warning every 10 minutes instead of one per poll.
- The bar's progress label no longer overlaps the Manage link on narrow screens.
- A visible bar no longer adds its own height as a page scroll.

## 5.0.2 - 2026-10-15

### Fixed

- Delayed jobs (such as Alarm Clock's self-rescheduling check) no longer count as waiting, so they don't keep the activity bar visible. They're now counted as "scheduled" and shown only while the bar is up for other activity. ([#1](https://github.com/justinholtweb/craft-queuebert/issues/1))

## 5.0.1 - 2026-07-19

### Fixed

- The activity bar's loading spinner now renders solid white so it reads clearly on the bar.

## 5.0.0 - 2026-07-17

### Added

- Initial release.
- Live control-panel bar that appears only while there is queue activity and hides when the queues go quiet.
- Aggregates running, waiting, and failed jobs across Craft's default queue and any custom queue runners registered as application components.
- Shows the current running job's description and progress.
- Settings for poll interval, whether waiting jobs count as activity, whether to surface failed jobs, and the per-queue inspect limit.
