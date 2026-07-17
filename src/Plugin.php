<?php

namespace justinholtweb\queuebert;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\web\View;
use justinholtweb\queuebert\assetbundles\QueuebertCpAsset;
use justinholtweb\queuebert\models\Settings;
use justinholtweb\queuebert\services\Queues;
use yii\base\Event;

/**
 * Queuebert — a live control-panel bar surfacing running and queued jobs
 * across Craft's default queue and any custom queue runners.
 *
 * @property Queues $queues
 * @property Settings $settings
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'queues' => Queues::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        Craft::$app->onInit(function (): void {
            $this->_registerCpBar();
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('queuebert/_settings', [
            'settings' => $this->getSettings(),
            'plugin' => $this,
        ]);
    }

    /**
     * Injects the activity bar's assets into every control-panel page. The bar
     * itself stays hidden until its poll finds queue activity.
     */
    private function _registerCpBar(): void
    {
        $request = Craft::$app->getRequest();

        if (!$request->getIsCpRequest() || $request->getIsLoginRequest()) {
            return;
        }

        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            function (): void {
                $view = Craft::$app->getView();
                $settings = $this->getSettings();

                $view->registerJsVar('QueuebertSettings', [
                    'pollInterval' => $settings->pollInterval,
                ]);
                $view->registerAssetBundle(QueuebertCpAsset::class);
            }
        );
    }
}
