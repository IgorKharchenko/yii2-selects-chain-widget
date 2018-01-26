<?php

namespace frontend\widgets\SelectsChainWidget;

use yii\web\AssetBundle;
use yii\helpers\Url;
use yii\web\View;

class SelectsChainAsset extends AssetBundle
{
    public $sourcePath = __DIR__;

    public $js = [
        '/src/js/SelectChain.js',
    ];

    public $jsOptions = [
        'position' => View::POS_END,
    ];
}