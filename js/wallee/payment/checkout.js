/**
 * wallee Magento 1
 *
 * This Magento extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
if (typeof MageWallee == 'undefined') {
	var MageWallee = {};
}

MageWallee.Checkout = {
	paymentPageUrl: null,
	paymentMethods: {},
	type: null,

	initialize: function() {
		new this.type();
	},

	registerMethod: function(code, configurationId, container) {
		this.paymentMethods[code] = {
			configurationId: configurationId,
			container: container,
			handler: null
		};
	}
};

MageWallee.Checkout.Type = Class.create({
	isSupportedPaymentMethod: function(code) {
		return code && code.startsWith('wallee_payment_');
	},

	getPaymentMethod: function(code) {
		return MageWallee.Checkout.paymentMethods[code];
	},

	createHandler: function(code, onStart, onValidation, onDone, onEnableSubmit, onDisableSubmit) {
		if (typeof window.IframeCheckoutHandler == 'undefined') {
			return;
		}

		if (this.isSupportedPaymentMethod(code) && !this.getPaymentMethod(code).handler) {
			if (typeof onStart == 'function') {
				onStart();
			}

			this.getPaymentMethod(code).handler = window.IframeCheckoutHandler(this.getPaymentMethod(code).configurationId);
			this.getPaymentMethod(code).handler.setEnableSubmitCallback(onEnableSubmit);
			this.getPaymentMethod(code).handler.setDisableSubmitCallback(onDisableSubmit);
			this.getPaymentMethod(code).handler.create(
				this.getPaymentMethod(code).container,
				function(validationResult) {
					if (typeof onValidation == 'function') {
						onValidation(validationResult);
					}
				}.bind(this),
				function() {
					if (typeof onDone == 'function') {
						onDone();
					}
				}
			);
		}
	},

	parseResponse: function(transport) {
		try {
			return transport.responseJSON || transport.responseText.evalJSON(true) || {};
		} catch (e) {
			return {};
		}
	},

	formatErrorMessages: function(messages) {
		var formattedMessage;
		if (typeof(messages) == 'object') {
			formattedMessage = messages.join("\n");
		} else if (Object.isArray(messages)) {
			formattedMessage = messages.join("\n").stripTags().toString();
		} else {
			formattedMessage = messages
		}

		return formattedMessage;
	}
});

document.observe(
	'dom:loaded',
	function() {
		MageWallee.Checkout.initialize();
	}
);