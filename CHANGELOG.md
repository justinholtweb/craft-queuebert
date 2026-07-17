# Release Notes for Queuebert

## 5.0.0 - 2026-07-17

### Added

- Initial release.
- Live control-panel bar that appears only while there is queue activity and hides when the queues go quiet.
- Aggregates running, waiting, and failed jobs across Craft's default queue and any custom queue runners registered as application components.
- Shows the current running job's description and progress.
- Settings for poll interval, whether waiting jobs count as activity, whether to surface failed jobs, and the per-queue inspect limit.
