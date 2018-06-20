/**
 * wallee Magento 1
 *
 * This Magento extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

MageWallee.Checkout.Type.TMFireCheckout = Class.create(
    MageWallee.Checkout.Type, {
    initialize : function () {
        Payment.prototype.switchMethod = Payment.prototype.switchMethod.wrap(this.switchMethod.bind(this));
        payment.switchMethod(payment.currentMethod);

        Payment.prototype.validate = Payment.prototype.validate.wrap(this.validate.bind(this));
    },

    /**
     * Initializes the payment iframe when the customer switches the payment method.
     */
    switchMethod : function (callOriginal, method) {
        callOriginal(method);
        this.createHandler(
            method, function () {
            checkout.setLoadWaiting('payment');
            }, function (validationResult) {
            if (validationResult.success) {
                this.createOrder();
            } else {
                checkout.setLoadWaiting(false);
            }
            }.bind(this), function () {
            checkout.setLoadWaiting(false);
            }
        );
    },

    validate : function (callOriginal) {
        var result = callOriginal();
        if (result && this.isSupportedPaymentMethod(payment.currentMethod)) {
            checkout.setLoadWaiting('payment');
            this.getPaymentMethod(payment.currentMethod).handler.validate();
            return false;
        } else {
            return result;
        }
    },

    createOrder : function () {
        $('review-please-wait').show();
        new Ajax.Request(
            checkout.urls.save, {
            method : 'post',
            parameters : Form.serialize(checkout.form),
            onSuccess : this.onOrderCreated.bind(this),
            onFailure : checkout.ajaxFailure.bind(checkout)
            }
        )
    },

    onOrderCreated : function (transport) {
        if (transport) {
            var response = this.parseResponse(transport);

            if (response.redirect || response.order_created) {
                this.getPaymentMethod(payment.currentMethod).handler.submit();
                return true;
            } else if (response.error) {
                alert(this.formatErrorMessages(response.error_messages));
                checkout.setLoadWaiting(false);
                $('review-please-wait').hide();
            } else {
                checkout.setReponse(transport);
            }
        }
    }
    }
);
MageWallee.Checkout.type = MageWallee.Checkout.Type.TMFireCheckout;