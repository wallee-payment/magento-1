/**
 * Wallee Magento
 *
 * This Magento extension enables to process payments with Wallee (https://wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 * @link https://github.com/wallee-payment/magento
 */

var walleePaymentLabelContainer = new Class.create();
walleePaymentLabelContainer.prototype = {
    initialize : function (containerId) {
        this.containerId = containerId;
        this.container = $(this.containerId);
        this.trigger = $$('#' + this.containerId + ' .wallee-payment-label-group')[0];
        
        Event.observe(this.trigger, 'click', this.toggle.bind(this));
    },

    open : function () {
        this.container.addClassName('active');
    },

    close : function () {
        this.container.removeClassName('active');
    },

    toggle : function () {
        if (this.isOpen()) {
            return this.close();
        } else {
            return this.open();
        }
    },

    isOpen : function () {
        return this.container.hasClassName('active');
    }
};