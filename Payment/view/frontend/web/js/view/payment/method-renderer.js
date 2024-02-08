define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'bharatx',
                component: 'BharatX_Payment/js/view/payment/method-renderer/Bharatxpayment'
            }
        );
        return Component.extend({});
    }
);