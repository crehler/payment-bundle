import template from './sw-order-detail.html.twig';

const { Component } = Shopware;

Component.override('sw-order-detail', {
    template,

    inject: ['CrPaymentRefundService'],

    data() {
        return {
            // Whether this order's provider can actually process refunds. Stays false
            // until the backend confirms support, so the tab never flashes for a gateway
            // (e.g. PayNow) that would fail with "unknown refund handler".
            crehlerRefundSupported: false,
        };
    },

    computed: {
        isCrehlerPayment() {
            // Universal marker: any order transaction carrying the bundle's gateway-id
            // custom field belongs to a provider extending AbstractPaymentPlugin. Split-payment
            // safe — scans all transactions, not just the most recent by createdAt.
            return (this.order?.transactions ?? []).some(
                (t) => t?.customFields?.crehler_payment_gateway_id,
            );
        },

        showCrehlerRefundTab() {
            return this.isCrehlerPayment && this.crehlerRefundSupported;
        },
    },

    watch: {
        // The order id is the stable signal — refire when navigating between orders.
        '$route.params.id': {
            immediate: true,
            handler() {
                this.loadCrehlerRefundSupport();
            },
        },

        isCrehlerPayment() {
            // transactions arrive asynchronously after the order loads; re-check once
            // we know it's a Crehler order.
            this.loadCrehlerRefundSupport();
        },
    },

    methods: {
        loadCrehlerRefundSupport() {
            const orderId = this.$route?.params?.id;

            if (!orderId || !this.isCrehlerPayment) {
                this.crehlerRefundSupported = false;

                return;
            }

            this.CrPaymentRefundService.isRefundSupported(orderId)
                .then((supported) => {
                    this.crehlerRefundSupported = supported;
                })
                .catch(() => {
                    // On error, fail closed: hide the tab rather than expose an action
                    // whose support we couldn't confirm.
                    this.crehlerRefundSupported = false;
                });
        },
    },
});
