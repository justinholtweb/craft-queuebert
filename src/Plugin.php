<?php

declare(strict_types=1);

namespace JustinHoltWeb\Queuebert;

use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Number;
use CraftCms\Cms\Ui\Controls\Textarea;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Heading;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use Override;

use function CraftCms\Cms\t;

/**
 * Queuebert — a live control-panel bar surfacing running and queued jobs
 * across Craft's tracked queues and any other Laravel queue.
 *
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public string $minVersionRequired = '5.0.2';

    public bool $hasCpSettings = true;

    /**
     * Published to public/vendor/justinholtweb/craft-queuebert/ and loaded on
     * every control-panel page. The bar stays hidden until a poll finds activity.
     */
    protected array $scripts = [
        __DIR__.'/../resources/js/queuebert.js' => 'js/queuebert.js',
    ];

    protected array $styles = [
        __DIR__.'/../resources/css/queuebert.css' => 'css/queuebert.css',
    ];

    public function boot(): void
    {
        $this->app->bind(Settings::class, fn (): Settings => $this->getSettings());
    }

    #[Override]
    protected static function createSettings(): Settings
    {
        return new Settings;
    }

    #[Override]
    public function settingsUi(UiContext $context = new UiContext): Ui
    {
        $field = fn (string $label, $control, ?string $instructions = null): Field => Field::make($label, $control)->instructions($instructions);

        return Ui::make([
            Heading::make('activity-bar', t('Activity bar', category: 'queuebert')),
            $field(
                t('Poll interval', category: 'queuebert'),
                Number::make('pollInterval')->min(1)->max(60)->step(1)->suffix(t('seconds', category: 'queuebert')),
                t('How often (in seconds) the bar checks for queue activity while a control-panel tab is open. Between 1 and 60.', category: 'queuebert'),
            )->required(),
            $field(
                t('Count waiting jobs as activity', category: 'queuebert'),
                Lightswitch::make('countWaiting'),
                t('Show the bar when jobs are queued but not yet running (e.g. no worker has picked them up).', category: 'queuebert'),
            ),
            $field(
                t('Surface failed jobs', category: 'queuebert'),
                Lightswitch::make('showFailed'),
                t('Show the bar (in an alert color) when there are failed jobs, so they aren’t missed.', category: 'queuebert'),
            ),
            Heading::make('queues', t('Queues', category: 'queuebert'))
                ->description(t('Craft’s tracked queues (the `trackedQueueNames` config setting) are always shown, with job descriptions and progress. Other Laravel queues only report counts.', category: 'queuebert')),
            $field(
                t('Discover queues automatically', category: 'queuebert'),
                Lightswitch::make('autoDiscover'),
                t('Watch every queue name that has jobs on a `database`-driver connection.', category: 'queuebert'),
            ),
            $field(
                t('Additional queues', category: 'queuebert'),
                Textarea::make('watchQueues')->rows(3)->monospace()->placeholder("imports\nredis:emails"),
                t('One per line: a queue name on the default connection, or `connection:queue`. Use this for Redis, SQS or Beanstalkd queues, which can’t be discovered.', category: 'queuebert'),
            ),
            Heading::make('advanced', t('Advanced', category: 'queuebert')),
            $field(
                t('Inspect limit', category: 'queuebert'),
                Number::make('inspectLimit')->min(1)->step(1),
                t('Maximum number of Craft jobs to inspect when building the summary. Raise only if you routinely have very large queues.', category: 'queuebert'),
            )->required(),
        ]);
    }
}
