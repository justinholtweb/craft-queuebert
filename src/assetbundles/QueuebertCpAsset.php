<?php

namespace justinholtweb\queuebert\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Front-end assets for the Queuebert control-panel activity bar.
 */
class QueuebertCpAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@justinholtweb/queuebert/resources';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/queuebert.js',
        ];

        $this->css = [
            'css/queuebert.css',
        ];

        parent::init();
    }
}
