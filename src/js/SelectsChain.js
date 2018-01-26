/**
 * Цепочка select-ов, при изменении которых отправляется ajax-запрос.
 * Если пользователь меняет текущий элемент в select-е,
 * то должен выполниться запрос на получение
 * всех доступных option-ов в соседнем select-е снизу.
 *
 * Цепочка здесь - это вереница из односвязных html-элементов select,
 * т.е. каждый взаимодействует только со своим соседом снизу.
 * Можно пройтись по всей цепочке вниз, начиная с какого-либо элемента.
 *
 * К примеру, тип прибора зависит от типа работ над прибором,
 * а тип работ зависит от типа доставки прибора.
 * Получаем вот такую цепочку:
 * Тип прибора -> Тип работ -> Тип доставки.
 * Верхний элемент = тип прибора, нижний элемент = тип доставки.
 *
 * Логика работы сего модуля такова.
 * При инициализации назначаются обработчики события 'onchange' на все элементы.
 * В success предполагается, что с серва вернулся вот такой объект:
 * {
 *     success: true,
 *     data: [здесь лежат все элементы]
 * }
 * В error же преполагается следующее:
 * {
 *     success: false,
 *     errorMessage: 'ACTHUNG!'
 * }
 *
 *
 * Логика обработчика.
 *
 * Перед ajax-запросом (функция beforeSend) все элементы,
 * находящиеся ниже в цепочке, disable-ятся.
 * В запросе по умолчанию отправляется объект:
 * {
 *     'data': 'value'
 * }
 * , где value - это текст внутри селектора.
 * Можно переопределить этот объект в элементе ajax.data (см. приимер ниже).
 *
 * После ajax-запроса элемент ниже текущего элемента enable-ится.
 * Элемент ниже заполняется данными из массива, полученного в ответе.
 *
 * Можно назначить другой обработчик на эти функции (см. массив ниже),
 * но логика выше всё равно выполнится.
 *
 * [
 * {
 *     selector: '#order-typeoforder',
 *     ajax: {
 *         url: '//site.com/order/get-types-of-device',
 *         method: 'POST',
 *         data: {
 *             'typeOfOrder': '{value}'
 *         },
 *         beforeSend: function() { ... } || null,
 *         afterSend: function() { ... } || null
 *     }
 * },
 * {
 *     selector: '#order-typeofdevice',
 *     ajax: {
 *         url: '//site.com/order/get-types-of-delivery',
 *         method: 'POST',
 *         data: {
 *             'deliveryType': '{value}'
 *         },
 *         beforeSend: function() { ... } || null,
 *         afterSend: function() { ... } || null
 *     }
 * },
 * {
 *     selector: '#order-typeofdelivery',
 *     ajax: {
 *         ...
 *     }
 * },
 * ...
 * ]
 *
 * beforeSend и afterSend - колбэки, выполняющиеся перед и после отправки ajax-запроса.
 * здесь dataFieldName означает примерно следующее:
 * $.ajax({
 *     data: {dataFieldName: $(selector).text()}
 * });
 * т.е. оно будет отправлено как ключ объекта
 * вместе с содержимым элемента с селектором 'selector'.
 *
 * @constructor
 */
class SelectsChain {
    constructor(chain) {
        this.validateParams(chain);

        this.chain = chain;

        for (let i = 0; i < chain.length; i++) {
            $(chain[i].selector).on('change', this.mainCallback.bind(this));
        }
    }

    mainCallback(event) {
        // todo должен быть селектор, а не просто id
        let selector = event.target.id;
        if ('#' !== selector[0]) {
            selector = '#' + selector;
        }
        let selectorIndex = this.getSelectorIndex(selector);

        this.activeSelector = selector;
        let neighborChainUnit = this.chain[selectorIndex + 1];
        if (neighborChainUnit) {
            this.neighborSelector = neighborChainUnit.selector;
        }

        let chainUnit     = this.chain[selectorIndex];
        let dataFieldName = Object.keys(chainUnit.ajax.data)[0] || 'data';

        let callbacks = this.getAjaxCallbacks(chainUnit);

        let selectorText = $(selector + ' :selected').text();
        let requestData                 = chainUnit.data || {};
        requestData['' + dataFieldName] = selectorText;
        requestData['_csrf-frontend']   = $('[name=\'_csrf-frontend\']').prop('value');

        $.ajax({
            url:        chainUnit.ajax.url,
            method:     chainUnit.ajax.method,
            data:       requestData,
            beforeSend: callbacks.beforeSend,
        })
            .done(callbacks.success.bind(this))
            .fail(callbacks.error.bind(this));
    }

    getAjaxCallbacks(chainUnit)
    {
        return {
            beforeSend: this.getBeforeSendCallback(chainUnit),
            success: this.successCallback.bind(this),
            error: this.errorCallback.bind(this)
        };
    }

    successCallback(data) {
        if (false === data.success) {
            console.warn('ACHTUNG!');
            console.warn(data.errorMessage);
        }

        if (this.neighborSelector) {
            let neighbor = $(this.neighborSelector);
            $(this.neighborSelector + ' option').remove();

            for (let i = 0; i < data.data.length; i++) {
                let item = data.data[i];
                neighbor.append('<option value="' + item + '">' + item + '</option>');
            }
        }

        let selectorIndex = this.getSelectorIndex(this.activeSelector);
        let chainUnit = this.chain[selectorIndex];
        let afterSend = this.getAfterSendCallback(chainUnit);
        afterSend();
    }

    errorCallback(jqXHR, textStatus) {
        console.warn('ACHTUNG!');
    }

    beforeSend(selector) {
        let selectorIndex = this.getSelectorIndex(selector);

        // disable-им все элементы снизу по цепочке
        for (let i = selectorIndex; i < this.chain.length; i++) {
            let sel = $(this.chain[i].selector);
            sel.prop('disabled', true);
        }

        // если есть сосед снизу -- очищаем все его option-ы и ставим 'Подождите...'
        if (this.neighborSelector) {
            let neighbor = $(this.neighborSelector);
            $(this.neighborSelector + ' option').remove();

            neighbor.append('<option value="0">Пожалуйста, подождите...</option>');
        }
    }

    afterSend(selector) {
        let selectorIndex = this.getSelectorIndex(selector);

        // enable-им элемент
        let select = $(this.chain[selectorIndex].selector);
        select.prop('disabled', false);

        // если цепочка закончилась -- значит выходим
        let hasNeighbour = this.chain[selectorIndex + 1];
        if (!hasNeighbour) {
            return;
        }

        // enable-им соседа снизу
        let neighborSel = $(this.chain[selectorIndex + 1].selector);
        neighborSel.prop('disabled', false);
    }

    getBeforeSendCallback(chainUnit) {
        let isFunction = ('function' === typeof chainUnit.beforeSend);

        if (isFunction) {
            return function () {
                this.beforeSend(chainUnit.selector);
            }.bind(this);
        } else {
            return function () {
                this.beforeSend(chainUnit.selector);
                chainUnit.ajax.beforeSend();
            }.bind(this);
        }
    }

    getAfterSendCallback(chainUnit) {
        let isFunction = ('function' === typeof chainUnit.afterSend);

        if (isFunction) {
            return function () {
                this.afterSend(chainUnit.selector);
            }.bind(this);
        } else {
            return function () {
                this.afterSend(chainUnit.selector);
                chainUnit.ajax.afterSend();
            }.bind(this);
        }
    }

    getSelectorIndex(selector) {
        for (let i = 0; i < this.chain.length; i++) {
            if (selector === this.chain[i].selector) {
                return i;
            }
        }

        return -1;
    }

    /**
     * Валидирует цепочку.
     */
    validateParams(chain) {
        let validator = new Validator();

        validator.isObject(chain);

        for (let i = 0; i < chain.length; i++) {
            validator.isString(chain[i].selector);

            let ajax = chain[i].ajax;
            validator.isObject(ajax);
            validator.isString(ajax.url);
            validator.isString(ajax.method);
            validator.isObject(ajax.data);
            if (ajax.beforeSend) {
                validator.isFunction(ajax.beforeSend);
            }
            if (ajax.afterSend) {
                validator.isFunction(ajax.afterSend);
            }
        }

        return true;
    }
}

function ValidatorException(message) {
    'use strict';

    this.message = message;
    this.name    = 'Исключение валидации';
}

class Validator {
    validateNestedFields(obj, nestedFields) {
        for (let i = 0; i < nestedFields.length; i++) {
            let element = nestedFields[i];
            this.validate(obj[element], typeof element);
        }
    }

    isObject(value) {
        this.validate(value, 'object');
    }

    isString(value) {
        this.validate(value, 'string');
    }

    isArray(value) {
        this.validate(value, 'array');
    }

    isFunction(value) {
        this.validate(value, 'function');
    }

    validate(value, expectedType) {
        let gotType = typeof value;

        if (expectedType !== gotType) {
            throw new ValidatorException('Expected ' + expectedType + ', got ' + gotType);
        }
    }
}