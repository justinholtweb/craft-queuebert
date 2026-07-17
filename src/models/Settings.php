<?php

namespace justinholtweb\queuebert\models;

use craft\base\Model;

/**
 * Queuebert settings.
 *
 * @property int $pollInterval  How often the bar polls for activity, in seconds.
 * @property bool $countWaiting Whether waiting (not-yet-running) jobs count as activity.
 * @property bool $showFailed   Whether to surface failed jobs in the bar.
 */
class Settings extends Model
{
    /**
     * @var int Seconds between activity polls while the CP is open.
     */
    public int $pollInterval = 3;

    /**
     * @var bool Treat waiting jobs as activity (show the bar even when no worker
     * is actively running a job yet).
     */
    public bool $countWaiting = true;

    /**
     * @var bool Surface failed jobs in the bar so they aren't missed.
     */
    public bool $showFailed = true;

    /**
     * @var int Max jobs to inspect per queue when building the summary.
     */
    public int $inspectLimit = 100;

    public function rules(): array
    {
        return [
            [['pollInterval', 'inspectLimit'], 'integer', 'min' => 1],
            [['pollInterval'], 'integer', 'max' => 60],
            [['countWaiting', 'showFailed'], 'boolean'],
            [['pollInterval', 'inspectLimit'], 'required'],
        ];
    }
}
