import template from './cr-payment-refund-card.html.twig';
import './cr-payment-refund-card.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('cr-payment-refund-card', {
    template,

    inject: {
        repositoryFactory: { from: 'repositoryFactory' },
        CrPaymentRefundService: { from: 'CrPaymentRefundService' },
        acl: { from: 'acl' },
    },

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        order: {
            type: Object,
            required: true,
        },
        capture: {
            type: Object,
            required: true,
        },
        totalAmount: {
            type: Number,
            required: true,
        },
        refundedAmount: {
            type: Number,
            required: true,
        },
        remainingAmount: {
            type: Number,
            required: true,
        },
    },

    data() {
        return {
            isLoading: false,
            showModal: false,
            // Predefined refund reasons the provider exposes (empty = free-text note only).
            refundReasons: [],
            refundReasonRequired: false,
        };
    },

    created() {
        this.loadRefundReasons();
    },

    computed: {
        refundRepository() {
            return this.repositoryFactory.create('order_transaction_capture_refund');
        },

        refundPositionRepository() {
            return this.repositoryFactory.create('order_transaction_capture_refund_position');
        },

        stateMachineStateRepository() {
            return this.repositoryFactory.create('state_machine_state');
        },

        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },

        currencyIsoCode() {
            return this.order?.currency ? this.order.currency.isoCode : 'EUR';
        },

        canCreateRefund() {
            return this.acl.can('order_refund.creator');
        },

        isFullyRefunded() {
            return this.remainingAmount <= 0;
        },

        isNewRefundDisabled() {
            return this.isLoading || this.isFullyRefunded || !this.canCreateRefund;
        },
    },

    methods: {
        loadRefundReasons() {
            const orderId = this.order?.id;
            if (!orderId) {
                return;
            }

            this.CrPaymentRefundService.getRefundReasons(orderId)
                .then(({ required, reasons }) => {
                    this.refundReasons = reasons;
                    this.refundReasonRequired = required;
                })
                .catch(() => {
                    // Non-fatal: fall back to free-text only rather than blocking refunds.
                    this.refundReasons = [];
                    this.refundReasonRequired = false;
                });
        },

        onOpenModal() {
            if (this.isNewRefundDisabled) {
                return;
            }

            this.showModal = true;
        },

        onCloseModal() {
            this.showModal = false;
        },

        onSubmitRefund(payload) {
            this.showModal = false;
            this.createRefund(payload);
        },

        async createRefund({ amount, reason, reasonCode, positions }) {
            this.isLoading = true;

            try {
                const refund = this.refundRepository.create(Shopware.Context.api);
                refund.captureId = this.capture.id;
                refund.amount = this.buildCalculatedPrice(amount);
                refund.reason = reason || null;
                // The free-text reason stays in refund.reason (Shopware); the predefined code
                // selected by the operator travels to the gateway via custom fields, which the
                // bundle handler reads into RefundCommand.reasonCode.
                if (reasonCode) {
                    refund.customFields = {
                        ...(refund.customFields ?? {}),
                        crehler_payment_refund_reason_code: reasonCode,
                    };
                }
                // The refund state machine has no API-side default initial state — set it
                // explicitly, or the DAL write rejects the entity (stateId must not be blank).
                const stateId = await this.resolveOpenRefundStateId();
                if (!stateId) {
                    throw new Error(this.$tc('cr-payment-refund.refund.error'));
                }
                refund.stateId = stateId;

                await this.refundRepository.save(refund, Shopware.Context.api);

                // Persist line-item positions (if any) before triggering the gateway call,
                // so the bundle handler can read them from the refund aggregate.
                for (const position of positions ?? []) {
                    const entity = this.refundPositionRepository.create(Shopware.Context.api);
                    entity.refundId = refund.id;
                    entity.orderLineItemId = position.orderLineItemId;
                    entity.quantity = position.quantity;
                    entity.amount = this.buildCalculatedPrice(position.amount);

                    // eslint-disable-next-line no-await-in-loop
                    await this.refundPositionRepository.save(entity, Shopware.Context.api);
                }

                await this.CrPaymentRefundService.process(refund.id);

                this.createNotificationSuccess({
                    message: this.$tc('cr-payment-refund.refund.success'),
                });

                this.$emit('refund-completed');
            } catch (error) {
                this.createNotificationError({
                    message: error?.response?.data?.errors?.[0]?.detail
                        || error?.message
                        || this.$tc('cr-payment-refund.refund.error'),
                });

                // Reload so a refund left in "failed" state by the processor shows up in history.
                this.$emit('refund-completed');
            } finally {
                this.isLoading = false;
            }
        },

        async resolveOpenRefundStateId() {
            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('technicalName', 'open'));
            criteria.addFilter(Criteria.equals('stateMachine.technicalName', 'order_transaction_capture_refund.state'));

            const result = await this.stateMachineStateRepository.search(criteria, Shopware.Context.api);

            return result.first()?.id ?? null;
        },

        buildCalculatedPrice(amount) {
            // No apiAlias / extensions — those are read-model fields the DAL write API rejects.
            return {
                unitPrice: amount,
                totalPrice: amount,
                quantity: 1,
                calculatedTaxes: [],
                taxRules: [],
            };
        },
    },
});
