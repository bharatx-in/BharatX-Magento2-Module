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
                template: 'Test_Testpayment/payment/testpayment',
                testDataFrameLoaded: false,
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
                return 'testpayment';
            },

            getTitle: function () {
                return window.checkoutConfig.payment.testpayment.title;
            },

            getTitleImage: function () {
                return window.checkoutConfig.payment.testpayment.titleImage;
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

                // self.getOrder("this_is_a_random_id");

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
                let requestURL = url.build('testpayment/standard/request');

                console.log(url);

                $.ajax({
                    type: 'POST',
                    url: requestURL,

                    /**
                     * Success callback
                     * @param {Object} response
                     */
                    success: function (response) {
                        console.log(response);

                        // fullScreenLoader.stopLoader();
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
                        console.log(response);
                        // fullScreenLoader.stopLoader();
                        self.isPaymentProcessing.reject(response.message);
                    }
                });
            },

            // getOrder: function (orderId) {
            //     var self = this,
            //         billing_address;
            //     this.messageContainer.clear();

            //     this.amount = quote.totals()['base_grand_total'];
            //     billing_address = quote.billingAddress();

            //     this.user = {
            //         name: billing_address.firstname + ' ' + billing_address.lastname,
            //         phoneNumber: billing_address.telephone,
            //     };

            //     const partnerId = "";
            //     const privateKey = "";

            //     const data = {
            //         transaction: {
            //             id: orderId,
            //             amount: this.amount,
            //             mode: "TEST", // TODO: to get test or live mode from adminhtml
            //             notes: {}
            //         },
            //         user: this.user
            //     }

            //     $.ajax({
            //         type: 'POST',
            //         url: url.build('test/standard/request'),
            //         // url: 'https://web-v2.bharatx.tech/merchant/transaction',
            //         beforeSend: function (xhr) {
            //             xhr.setRequestHeader("Authorization", "Basic " + btoa(partnerId + ":" + privateKey));
            //         },
            //         contentType: 'application/json',
            //         dataType: 'json',
            //         data: JSON.stringify(data),
            //         /**
            //          * Success callback
            //          * @param {Object} response
            //          */
            //         success: function (response) {
            //             console.log(response);
            //             self.doCheckoutPayment(response);
            //         },

            //         /**
            //          * Error callback
            //          * @param {*} response
            //          */
            //         error: function (response) {
            //             // self.isPaymentProcessing.reject(response.message);
            //             console.error("error", response)
            //         }
            //     });
            // },

            doCheckoutPayment: function (response) {
                var self = this;

                $.mage.redirect(response.redirectUrl);
                return;
            },
        });
    }
);