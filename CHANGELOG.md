# Release Notes for Queuebert

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
