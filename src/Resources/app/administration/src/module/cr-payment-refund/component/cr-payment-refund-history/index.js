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

// Shopware ships no pl-PL translations for the refund state machine, so the state name
// coming from the entity renders in English inside an otherwise Polish admin. These are
// our own snippets, keyed by technical name.
const STATE_SNIPPETS = {
    open: 'cr-payment-refund.history.state.open',
    in_progress: 'cr-payment-refund.history.state.inProgress',
    completed: 'cr-payment-refund.history.state.completed',
    cancelled: 'cr-payment-refund.history.state.cancelled',
    failed: 'cr-payment-refund.history.state.failed',
};

// Where the refund module stores the predefined reason code chosen by the operator.
// Kept in sync with AbstractPaymentMethodHandler::REFUND_REASON_CODE_FIELD.
const REASON_CODE_FIELD = 'crehler_payment_refund_reason_code';

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

            // The second argument of $tc is the plural CHOICE, and the i18n library also
            // feeds it into {count}. Passing a hard-coded 0 therefore rendered "0 zwrotów"
            // no matter how many refunds there were (WT-910) — the counter contradicted
            // the rows right next to it.
            //
            // Polish needs a third form on top of one/many: 2-4 takes "zwroty", except for
            // the teens (12-14) which fall back to "zwrotów". The branching is done here
            // rather than in the snippets because this component already decides the
            // count === 1 case itself — one convention, one place.
            const lastDigit = count % 10;
            const lastTwoDigits = count % 100;
            const isFewForm = lastDigit >= 2 && lastDigit <= 4
                && (lastTwoDigits < 12 || lastTwoDigits > 14);

            if (count === 1) {
                return this.$tc('cr-payment-refund.history.countOne');
            }

            if (isFewForm) {
                return this.$tc('cr-payment-refund.history.countFew', count, { count });
            }

            return this.$tc('cr-payment-refund.history.countMany', count, { count });
        },
    },

    methods: {
        stateLabel(refund) {
            const technicalName = refund.stateMachineState?.technicalName;
            const snippet = STATE_SNIPPETS[technicalName];

            if (snippet) {
                return this.$tc(snippet);
            }

            return refund.stateMachineState?.translated?.name
                || refund.stateMachineState?.name
                || technicalName
                || '-';
        },

        /**
         * The native `reason` field only holds the operator's free-text note. When a
         * provider requires a predefined reason the operator picks a code instead, which
         * the module stores in custom fields — so a refund with a reason showed a dash
         * here and the history was useless as an audit trail (WT-910).
         */
        reasonLabel(refund) {
            return refund.reason
                || refund.customFields?.[REASON_CODE_FIELD]
                || '–';
        },

        statusVariant(refund) {
            return STATE_VARIANTS[refund.stateMachineState?.technicalName] ?? 'neutral';
        },
    },
});
