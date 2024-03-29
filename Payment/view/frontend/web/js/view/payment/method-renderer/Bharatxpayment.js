define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'jquery',
        'ko',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/set-payment-information',
        'mage/url',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/model/shipping-save-processor'
    ],
    function (Component, quote, $, ko, additionalValidators, setPaymentInformationAction, url, customer, placeOrderAction, fullScreenLoader, messageList) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'BharatX_Payment/payment/Bharatxpayment',
                BharatxDataFrameLoaded: false,
                cf_response: {
                    transaction: {},
                    order: {}
                }
            },

            context: function () {
                return this;
            },

            isShowLegend: function () {
                return true;
            },

            getCode: function () {
                return 'bharatx';
            },

            getTitle: function () {
                return window.checkoutConfig.payment.Bharatx.title;
            },

            getFrontendTitle: function () {
                return window.checkoutConfig.payment.Bharatxpayment.frontendTitle;
            },

            getTitleImage: function () {
                return window.checkoutConfig.payment.Bharatxpayment.titleImage;
            },

            isActive: function () {
                return true;
            },

            isAvailable: function () {
                return this.testDataFrameLoaded;
            },

            handleError: function (error) {
                if (_.isObject(error)) {
                    this.messageContainer.addErrorMessage(error);
                } else {
                    this.messageContainer.addErrorMessage({
                        message: error
                    });
                }
            },

            initObservable: function () {
                var self = this._super();

                if (!self.testDataFrameLoaded) {

                    self.testDataFrameLoaded = true;
                }
                return self;
            },

            placeOrder: function (event) {
                var self = this;

                console.log(self.orderId);

                if (event.preventDefault) {
                    event.preventDefault();
                }

                if (!self.orderId) {
                    this.isPlaceOrderActionAllowed(false);
                    this.getPlaceOrderDeferredObject()
                        .fail(
                            function () {
                                console.log("failed");
                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(
                            function (orderId) {
                                console.log(orderId);
                                self.getOrder(orderId);
                                self.orderId = orderId;
                            }
                        );
                } else {
                    self.getOrder(self.orderId);
                }

                return;
            },

            getOrder: function(orderId) {
                var self = this;
                let requestURL = url.build('Bharatxpayment/standard/request');

                this.isPaymentProcessing = $.Deferred();

                $.when(this.isPaymentProcessing).fail(
                    function (result) {
                        self.isPlaceOrderActionAllowed(true);
                        console.log(result);
                        self.handleError(result);
                    }
                );

                $.ajax({
                    type: 'POST',
                    url: requestURL,

                    /**
                     * Success callback
                     * @param {Object} response
                     */
                    success: function (response) {
                        console.log(response);

                        if (response.success) {
                           self.doCheckoutPayment(response);
                        } else {
                            self.isPaymentProcessing.reject(response.message);
                        }
                    },

                    /**
                     * Error callback
                     * @param {*} response
                     */
                    error: function (response) {
                        const message = response.responseJSON.message;
                        console.log(response.responseJSON);
                        self.isPaymentProcessing.reject(message);
                    }
                });
            },

            doCheckoutPayment: function (response) {
                var self = this;

                $.mage.redirect(response.redirectUrl);
                return;
            },
        });
    }
);