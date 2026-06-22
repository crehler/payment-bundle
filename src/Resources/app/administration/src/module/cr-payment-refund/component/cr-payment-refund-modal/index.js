import template from './cr-payment-refund-modal.html.twig';
import './cr-payment-refund-modal.scss';

const { Component } = Shopware;

function round2(value) {
    return Math.round(value * 100) / 100;
}

Component.register('cr-payment-refund-modal', {
    template,

    props: {
        order: {
            type: Object,
            required: true,
        },
        remainingAmount: {
            type: Number,
            required: true,
        },
        // Predefined reasons from the provider: [{ code, label }]. Empty = no selector.
        reasons: {
            type: Array,
            required: false,
            default: () => [],
        },
        // Whether picking a predefined reason is mandatory before submitting.
        reasonRequired: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            // 'full' = whole remaining amount, 'partial' = free amount, 'lineItems' = per position
            mode: 'full',
            amount: this.remainingAmount,
            // Operator's free-text note — stored on the Shopware refund entity, unchanged.
            reason: '',
            // Selected predefined reason code — sent to the gateway via the provider.
            reasonCode: '',
            items: [],
        };
    },

    created() {
        // Only product line items are refundable per-position; shipping/discount rows
        // are reflected in the amount-based path instead.
        this.items = (this.order?.lineItems ?? [])
            .filter((item) => item.type === 'product')
            .map((item) => ({
                id: item.id,
                label: item.label,
                productNumber: item.payload?.productNumber ?? '',
                unitPrice: item.unitPrice ?? 0,
                maxQuantity: item.quantity ?? 1,
                selected: false,
                quantity: item.quantity ?? 1,
            }));
    },

    computed: {
        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },

        currencyIsoCode() {
            return this.order?.currency ? this.order.currency.isoCode : 'EUR';
        },

        hasLineItems() {
            return this.items.length > 0;
        },

        hasReasons() {
            return this.reasons.length > 0;
        },

        reasonMissing() {
            // Only blocks when the provider requires a predefined reason and none is picked.
            return this.reasonRequired && this.hasReasons && !this.reasonCode;
        },

        selectedPositions() {
            return this.items
                .filter((item) => item.selected)
                .map((item) => {
                    // Clamp to a whole number within [0, maxQuantity] so a tampered model
                    // can't emit out-of-range or non-numeric quantities to the backend.
                    const quantity = Math.max(
                        0,
                        Math.min(Math.floor(Number(item.quantity) || 0), item.maxQuantity),
                    );

                    return {
                        orderLineItemId: item.id,
                        quantity,
                        amount: round2(item.unitPrice * quantity),
                    };
                })
                .filter((position) => position.quantity > 0);
        },

        effectiveAmount() {
            if (this.mode === 'lineItems') {
                return round2(this.selectedPositions.reduce((acc, p) => acc + p.amount, 0));
            }

            if (this.mode === 'partial') {
                return round2(Number(this.amount) || 0);
            }

            return round2(this.remainingAmount);
        },

        exceedsRemaining() {
            return this.effectiveAmount > round2(this.remainingAmount);
        },

        canSubmit() {
            return this.effectiveAmount > 0 && !this.exceedsRemaining && !this.reasonMissing;
        },

        isSubmitDisabled() {
            return !this.canSubmit;
        },
    },

    methods: {
        lineAmount(item) {
            return round2(item.unitPrice * item.quantity);
        },

        formatCurrency(value) {
            return this.currencyFilter(value, this.currencyIsoCode);
        },

        selectMode(value) {
            this.mode = value;
        },

        incAmount() {
            const next = Math.min((Number(this.amount) || 0) + 1, round2(this.remainingAmount));
            this.amount = round2(Math.max(next, 0));
        },

        decAmount() {
            const next = Math.max((Number(this.amount) || 0) - 1, 0);
            this.amount = round2(next);
        },

        onConfirm() {
            this.$emit('submit', {
                amount: this.effectiveAmount,
                reason: this.reason.trim(),
                reasonCode: this.reasonCode || null,
                positions: this.mode === 'lineItems' ? this.selectedPositions : [],
            });
        },

        onClose() {
            this.$emit('close');
        },
    },
});
