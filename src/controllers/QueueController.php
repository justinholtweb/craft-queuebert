<?php

namespace justinholtweb\queuebert\controllers;

use craft\web\Controller;
use justinholtweb\queuebert\Plugin;
use yii\web\Response;

/**
 * Serves the live queue-activity summary polled by the control-panel bar.
 */
class QueueController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = false;

    /**
     * Returns the aggregated queue activity as JSON.
     *
     * Any authenticated CP user may read this; it only exposes job counts,
     * descriptions and progress — the same information Craft's own queue HUD
     * shows.
     */
    public function actionInfo(): Response
    {
        $this->requireCpRequest();
        $this->requireAcceptsJson();

        $activity = Plugin::getInstance()->queues->getActivity();

        return $this->asJson($activity);
    }
}
