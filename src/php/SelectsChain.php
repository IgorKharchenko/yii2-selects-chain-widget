<?php

namespace hatand\widgets\SelectsChainWidget;

use yii\base\Widget;
use yii\web\View;
use hatand\widgets\SelectsChainWidget\SelectsChainAsset;
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

        $formattedChain = $this->_removeQuotesBetweenFunction([
            'beforeSend',
            'afterSend',
        ]);
        $js = 'new SelectsChain(' . $formattedChain . ');';
        $view->registerJs($js, View::POS_END);

        SelectsChainAsset::register($view);

        return '';
    }

    /**
     * Делает вот это:
     * [
     *     'beforeSend' => 'function() { return 'ahaha' }',
     *     'afterSend'  => 'function() { return 'ahaha' }',
     * ]
     * =>
     * {
     *     "beforeSend": function () { return 'ahaha' }
     *     "afterSend": function () { return 'ahaha' }
     * }
     * JsExpression к сожалению здесь бессилен,
     * пушо колбэки хранятся внутри вложенных массивов,
     * и после json_encode их оттуда уже просто так не вытащишь.
     *
     * @param array $callbackNames массив названий колбэков.
     *
     * @return string
     */
    private function _removeQuotesBetweenFunction(array $callbackNames): string
    {
        $jsonChain = json_encode($this->chain);

        $callbacksRegex = implode('|', $callbackNames);

        $pattern = "/['\"](" . $callbacksRegex . ")['\"][\s\t\r\n]*:[\s\t\r\n]*['\"]([^}]+})[\s\t\r\n]*['\"]/u";
        $replace = '"$1": $2';

        $replaced = preg_replace($pattern, $replace, $jsonChain);
        $replaced = str_replace([
            '\r',
            '\n',
            "\r",
            "\n",
        ], '', $replaced);

        return $replaced;
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
                Assertion::isArray($ajax['data']);
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