<?php

namespace hatand\widgets\SelectsChainWidget;

use yii\web\AssetBundle;
use yii\helpers\Url;
use yii\web\View;

class SelectsChainAsset extends AssetBundle
{
    public $sourcePath = "@vendor/hatand/yii2-selects-chain-widget";

    public $js = [
        'src/js/SelectsChain.js',
    ];

    public $jsOptions = [
        'position' => View::POS_END,
    ];
}