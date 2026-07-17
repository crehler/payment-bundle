import template from './cr-payment-gateway-details.html.twig';
import './cr-payment-gateway-details.scss';

const { Component } = Shopware;

// Kept in sync with refundBadgeClass()/refundBadgeLabel() below — a status missing
// from this list falls back to raw display instead of a translated badge.
const KNOWN_REFUND_STATUS_LEVELS = ['completed', 'in_progress', 'failed', 'cancelled'];

/**
 * Read-only "Szczegóły płatności (bramka)" section, injected into the order
 * "Szczegóły" tab's payment card. Loads provider-agnostic GatewayPaymentDetails for the
 * order; an order may have several Crehler-handled transactions (retries / payment
 * changes) — they are selectable, newest shown by default.
 */
Component.register('cr-payment-gateway-details', {
    template,

    inject: ['CrPaymentGatewayDetailsService'],

    props: {
        orderId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            isLoading: false,
            hasError: false,
            transactions: [],
            selected: null,
            details: null,
            copied: false,
            lastRefresh: null,
        };
    },

    computed: {
        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },

        hasTransactions() {
            return this.transactions.length > 0;
        },

        hasMultipleTransactions() {
            return this.transactions.length > 1;
        },

        statusBadgeClass() {
            return {
                paid: 'cr-gw__badge--success',
                pending: 'cr-gw__badge--warning',
                failed: 'cr-gw__badge--danger',
                refunded: 'cr-gw__badge--neutral',
            }[this.details?.statusLevel] ?? 'cr-gw__badge--neutral';
        },

        statusBadgeLabel() {
            const key = {
                paid: 'paid',
                pending: 'pending',
                failed: 'failed',
                refunded: 'refunded',
            }[this.details?.statusLevel] ?? 'unknown';

            return this.$tc(`cr-payment-gateway.badge.${key}`);
        },

        amountFormatted() {
            if (this.details?.amount === null || this.details?.amount === undefined) {
                return '—';
            }

            return this.currencyFilter(this.details.amount, this.details.currency || 'PLN');
        },

        modeLabel() {
            return this.details?.sandbox
                ? this.$tc('cr-payment-gateway.mode.sandbox')
                : this.$tc('cr-payment-gateway.mode.production');
        },

        refunds() {
            return this.details?.refunds ?? [];
        },

        hasRefunds() {
            return this.refunds.length > 0;
        },
    },

    created() {
        this.load();
    },

    beforeDestroy() {
        clearTimeout(this._copyTimer);
    },

    methods: {
        load(orderTransactionId = null) {
            this.isLoading = true;
            this.hasError = false;

            return this.CrPaymentGatewayDetailsService
                .getDetails(this.orderId, orderTransactionId)
                .then((res) => {
                    this.transactions = res.transactions ?? [];
                    this.selected = res.selected ?? null;
                    this.details = res.details ?? null;
                })
                .catch(() => {
                    this.hasError = true;
                })
                .finally(() => {
                    this.isLoading = false;
                    this.lastRefresh = this.formatTime(new Date());
                    this.copied = false;
                });
        },

        onSelectTransaction(event) {
            this.load(event.target.value);
        },

        onRefresh() {
            if (this.isLoading) {
                return;
            }
            this.load(this.selected);
        },

        copyId() {
            const id = this.details?.gatewayId;
            if (!id) {
                return;
            }
            try {
                if (navigator.clipboard?.writeText) {
                    navigator.clipboard.writeText(id);
                }
            } catch (e) {
                // ignore clipboard failures (insecure context etc.)
            }
            this.copied = true;
            clearTimeout(this._copyTimer);
            this._copyTimer = setTimeout(() => { this.copied = false; }, 1600);
        },

        formatTime(date) {
            const p = (n) => String(n).padStart(2, '0');
            return `${p(date.getHours())}:${p(date.getMinutes())}:${p(date.getSeconds())}`;
        },

        refundBadgeClass(refund) {
            return {
                completed: 'cr-gw__badge--success',
                in_progress: 'cr-gw__badge--warning',
                failed: 'cr-gw__badge--danger',
                cancelled: 'cr-gw__badge--neutral',
            }[refund.statusLevel] ?? 'cr-gw__badge--neutral';
        },

        refundBadgeLabel(refund) {
            if (!KNOWN_REFUND_STATUS_LEVELS.includes(refund.statusLevel)) {
                return refund.rawStatus || refund.statusLevel;
            }

            return this.$tc(`cr-payment-gateway.refunds.${refund.statusLevel}`);
        },

        refundAmountFormatted(refund) {
            return this.currencyFilter(refund.amount, this.details?.currency || 'PLN');
        },
    },
});
