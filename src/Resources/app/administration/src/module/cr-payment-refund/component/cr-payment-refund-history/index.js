import template from './cr-payment-refund-history.html.twig';
import './cr-payment-refund-history.scss';

const { Component } = Shopware;

// Maps a refund state-machine technical name to a status-pill variant.
const STATE_VARIANTS = {
    open: 'neutral',
    in_progress: 'progress',
    completed: 'done',
    cancelled: 'neutral',
    failed: 'danger',
};

Component.register('cr-payment-refund-history', {
    template,

    props: {
        order: {
            type: Object,
            required: true,
        },
        refunds: {
            type: Array,
            required: true,
        },
    },

    computed: {
        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },

        currencyIsoCode() {
            return this.order?.currency ? this.order.currency.isoCode : 'EUR';
        },

        hasRefunds() {
            return this.refunds.length > 0;
        },

        historyCountLabel() {
            const count = this.refunds.length;

            return count === 1
                ? this.$tc('cr-payment-refund.history.countOne')
                : this.$tc('cr-payment-refund.history.countMany', 0, { count });
        },
    },

    methods: {
        stateLabel(refund) {
            return refund.stateMachineState?.translated?.name
                || refund.stateMachineState?.name
                || refund.stateMachineState?.technicalName
                || '-';
        },

        statusVariant(refund) {
            return STATE_VARIANTS[refund.stateMachineState?.technicalName] ?? 'neutral';
        },
    },
});
