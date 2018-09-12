/**
 * wallee Magento 1
 *
 * This Magento extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
MageWallee.Checkout.Type.MagentoOnePage = Class.create(
	MageWallee.Checkout.Type, {
		originalPaymentSave: function() {},

		initialize: function() {
			Payment.prototype.switchMethod = Payment.prototype.switchMethod.wrap(this.switchMethod.bind(this));

			this.originalPaymentSave = Payment.prototype.save.bind(payment);
			Payment.prototype.save = Payment.prototype.save.wrap(this.savePayment.bind(this));

			Review.prototype.save = Review.prototype.save.wrap(this.placeOrder.bind(this));
		},

		/**
		 * Initializes the payment iframe when the customer switches the payment method.
		 */
		switchMethod: function(callOriginal, method) {
			callOriginal(method);
			this.createHandler(
				method,
				function() {
					checkout.setLoadWaiting('payment');
				},
				function(validationResult) {
					checkout.setLoadWaiting(false);
					if (validationResult.success) {
						this.originalPaymentSave();
					}
				}.bind(this),
				function() {
					checkout.setLoadWaiting(false);
				},
				function(){
					var container = $('payment-buttons-container');
					container.removeClassName('disabled');
                    container.setStyle({opacity:1});
                    this._disableEnableAll(container, false);
				}.bind(this),
				function(){
					var container = $('payment-buttons-container');
		            container.addClassName('disabled');
		            container.setStyle({opacity:.5});
		            this._disableEnableAll(container, true);
				}.bind(this)
			);
		},
		
		_disableEnableAll: function(element, isDisabled) {
	        var descendants = element.descendants();
	        for (var k in descendants) {
	            descendants[k].disabled = isDisabled;
	        }
	        element.disabled = isDisabled;
	    },

		/**
		 * Validates the payment information when the customer saves the payment method.
		 */
		savePayment: function(callOriginal) {
			if (this.isSupportedPaymentMethod(payment.currentMethod) && this.getPaymentMethod(payment.currentMethod).handler) {
				checkout.setLoadWaiting('payment');
				this.getPaymentMethod(payment.currentMethod).handler.validate();
				return false;
			} else {
				callOriginal();
			}
		},

		/**
		 * Sends the payment information to wallee after the customer submitted the order.
		 */
		placeOrder: function(callOriginal) {
			if (this.isSupportedPaymentMethod(payment.currentMethod)) {
				if (checkout.loadWaiting != false) {
					return;
				}

				checkout.setLoadWaiting('review');
				var params = Form.serialize(payment.form);
				if (review.agreementsForm) {
					params += '&' + Form.serialize(review.agreementsForm);
				}

				params.save = true;
				new Ajax.Request(
					review.saveUrl, {
						method: 'post',
						parameters: params,
						onSuccess: this.onOrderCreated.bind(this),
						onFailure: function() {
							review.onComplete();
							checkout.ajaxFailure();
						}
					}
				);
			} else {
				callOriginal();
			}
		},

		onOrderCreated: function(transport) {
			if (transport) {
				var response = this.parseResponse(transport);

				if (response.success) {
					if (this.getPaymentMethod(payment.currentMethod).handler) {
						this.getPaymentMethod(payment.currentMethod).handler.submit();
					} else {
						location.href = MageWallee.Checkout.paymentPageUrl + '&paymentMethodConfigurationId=' + this.getPaymentMethod(payment.currentMethod).configurationId;
					}
					return;
				} else {
					if (response.error_messages) {
						alert(this.formatErrorMessages(response.error_messages));
					}
					checkout.setLoadWaiting(false);
				}

				if (response.update_section) {
					$('checkout-' + response.update_section.name + '-load').update(response.update_section.html);
				}

				if (response.goto_section) {
					checkout.gotoSection(response.goto_section, true);
				}
			}
		}
	}
);
MageWallee.Checkout.type = MageWallee.Checkout.Type.MagentoOnePage;