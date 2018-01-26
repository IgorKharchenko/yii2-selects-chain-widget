<?php

namespace hatand\widgets\SelectsChainWidget;

use yii\base\Widget;
use yii\web\View;
use hatand\SelectsChainWidget\SelectsChainAsset;
use yii\web\JsExpression;
use Assert\Assertion;
use Assert\AssertionFailedException;

class SelectsChain extends Widget
{
    /**
     * @var array
     */
    public $chain;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->_validateChain($this->chain);
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $view = $this->getView();

        foreach ($this->chain as $chainUnit) {
            if (isset($chainUnit['beforeSend'])) {
                $chainUnit->beforeSend = new JsExpression($chainUnit->beforeSend);
            }
            if (isset($chainUnit['afterSend'])) {
                $chainUnit->afterSend = new JsExpression($chainUnit->afterSend);
            }
        }

        $js = 'new SelectsChain(' . json_encode($this->chain) . ');';
        $view->registerJs($js, View::POS_END);

        SelectsChainAsset::register($view);

        return '';
    }

    /**
     * Валидирует цепочку из select-ов.
     *
     * @param array $chain цепочка.
     *
     * @throws AssertionFailedException в случае ошибки валидации.
     */
    private function _validateChain(array $chain)
    {
        Assertion::isArray($chain);

        foreach ($chain as $chainUnit) {
            Assertion::string($chainUnit['selector']);
            Assertion::isArray($chainUnit['ajax']);

            $ajax = $chainUnit['ajax'];
            Assertion::string($ajax['url']);
            Assertion::string($ajax['method']);

            if (!empty($ajax['data'])) {
                Assertion::objectOrClass($ajax['data']);
            }
            if (isset($ajax['beforeSend'])) {
                Assertion::string($ajax['beforeSend']);
            }
            if (isset($ajax['afterSend'])) {
                Assertion::string($ajax['afterSend']);
            }
        }
    }
}