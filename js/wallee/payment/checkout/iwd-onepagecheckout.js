/**
 * Wallee Magento
 *
 * This Magento extension enables to process payments with Wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

MageWallee.Checkout.Type.IWDOnePageCheckout = Class.create(
    MageWallee.Checkout.Type, {
    originalSavePayment : function () {},

    initialize : function () {
        Payment.prototype.switchMethod = Payment.prototype.switchMethod.wrap(this.switchMethod.bind(this));

        this.originalSavePayment = IWD.OPC.savePayment;
        IWD.OPC.savePayment = IWD.OPC.savePayment.wrap(this.savePayment.bind(this));

        IWD.OPC.prepareOrderResponse = IWD.OPC.prepareOrderResponse.wrap(this.prepareOrderResponse.bind(this));
    },

    lockPlaceOrder : function () {
        IWD.OPC.Checkout.lockPlaceOrder();
    },

    unlockPlaceOrder : function () {
        IWD.OPC.saveOrderStatus = false;
        IWD.OPC.Checkout.hideLoader();
        IWD.OPC.Checkout.unlockPlaceOrder();
    },

    /**
     * Initializes the payment iframe when the customer switches the payment method.
     */
    switchMethod : function (callOriginal, method) {
        callOriginal(method);
        this.createHandler(
            method, function () {
            this.lockPlaceOrder();
            }.bind(this), function (validationResult) {
            if (validationResult.success) {
                this.originalSavePayment();
            } else {
                this.unlockPlaceOrder();
            }
            }.bind(this), function () {
            this.unlockPlaceOrder();
            }.bind(this)
        );
    },

    /**
     * Validates the payment information when the customer saves the payment method.
     */
    savePayment : function (callOriginal) {
        if (IWD.OPC.saveOrderStatus === true && this.isSupportedPaymentMethod(payment.currentMethod)) {
            this.getPaymentMethod(payment.currentMethod).handler.validate();
            return false;
        }

        callOriginal();
    },

    /**
     * Sends the payment information to wallee after the customer submitted the order.
     */
    prepareOrderResponse : function (callOriginal, response) {
        if (this.isSupportedPaymentMethod(payment.currentMethod)) {
            if (!response.error && response.redirect) {
                 this.getPaymentMethod(payment.currentMethod).handler.submit();
                return;
            }
        }

        callOriginal(response);
    }
    }
);
MageWallee.Checkout.type = MageWallee.Checkout.Type.IWDOnePageCheckout;