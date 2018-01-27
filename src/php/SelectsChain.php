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

        $js = $this->_getMainJs($this->chain);
        $view->registerJs($js, View::POS_END);

        SelectsChainAsset::register($view);

        return '';
    }

    /**
     * Возвращает js, создающий конфигурацию цепочки и
     *
     * @param array $chain
     *
     * @return string
     */
    private function _getMainJs(array $chain): string
    {
        $callbacks = $this->_getCallbacksFromChain($chain);

        $mainJs = 'let chainConfiguration = (' . json_encode($chain) . '); ';
        $mainJs .= $this->_setCallbacksToJs($callbacks);
        $mainJs .= 'let selectsChain = new SelectsChain(chainConfiguration)';

        return $mainJs;
    }

    /**
     * Возвращает список колбэков из цепочек вот такого вида:
     * [
     *     [
     *         'index' => 'индекс элемента цепочки',
     *         'callbackName' => 'название колбэка',
     *         'callbackContent' => new JsExpression('string-овое содержимое колбэка'),
     *     ],
     *     ...
     * ]
     *
     * @param array $chain цепочка.
     *
     * @return array
     */
    private function _getCallbacksFromChain(array $chain): array
    {
        $allowedCallbacks = [
            'beforeSend',
            'afterSend',
        ];

        $callbacks = [];

        $chainLength = count($chain);
        for ($i = 0; $i < $chainLength - 1; $i ++) {
            $ajax = $chain[$i]['ajax'];

            foreach ($allowedCallbacks as $callbackName) {
                if (!isset($ajax[$callbackName])) {
                    continue;
                }

                if (isset($ajax[$callbackName])) {
                    $callbacks[] = [
                        'index'           => $i,
                        'callbackName'    => $callbackName,
                        'callbackContent' => new JsExpression($ajax[$callbackName]),
                    ];
                }
            }
        }
        $lastChainUnit = $chain[$chainLength - 1];
        if ($lastChainUnit['change']) {
            $callbacks[] = [
                'index'           => $chainLength - 1,
                'callbackName'    => 'change',
                'callbackContent' => $lastChainUnit['change'],
            ];
        }

        return $callbacks;
    }

    /**
     * Переопределяет колбэки в js-объекте chainConfiguration
     * из string-ов в function-ы.
     *
     * @param array $callbacks колбэки (см. _getCallbacksFromChain)
     *
     *
     * @return string
     *
     * @see _getCallbacksFromChain();
     */
    private function _setCallbacksToJs(array $callbacks): string
    {
        $js = '';

        foreach ($callbacks as $arr) {

            $template = 'chainConfiguration[%u].ajax.%s = %s; ';
            if ('change' === $arr['callbackName']) {
                $template = str_replace('.ajax', '', $template);
            }

            $js .= sprintf($template, $arr['index'], $arr['callbackName'], $arr['callbackContent']);
        }

        return $js;
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

        // последний элемент не содержит колбэков beforeSend и afterSend
        $chainLength = count($chain);
        for ($i = 0; $i < $chainLength; $i ++) {
            $chainUnit = $chain[$i];

            Assertion::string($chainUnit['selector']);
            // последний элемент может содержать колбэк change
            if ($i === $chainLength - 1) {
                if (isset($chainUnit['change'])) {
                    Assertion::string($chainUnit['change']);
                }

                continue;
            }

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