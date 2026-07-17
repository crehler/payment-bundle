import template from './cr-payment-refund-detail.html.twig';
import './cr-payment-refund-detail.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

// Only refunds the gateway has actually confirmed consume the refundable balance.
// 'open' means no confirmed gateway response yet (including a refund stuck there by a
// broken/unsupported handler) and must NOT count as refunded money.
const GATEWAY_CONFIRMED_STATES = ['completed', 'in_progress'];

Component.register('cr-payment-refund-detail', {
    template,

    inject: {
        repositoryFactory: { from: 'repositoryFactory' },
        CrPaymentRefundService: { from: 'CrPaymentRefundService' },
        // Provided by the core sw-order-detail page — reloads the order with the full
        // association set + version context, so we never overwrite the store with an
        // under-associated entity.
        swOrderDetailOnReloadEntityData: { from: 'swOrderDetailOnReloadEntityData', default: null },
    },

    data() {
        return {
            capture: null,
            isLoading: false,
            hasError: false,
            // null = unknown (still checking); false = provider has no refund handler.
            // Guards against reaching the refund card via a direct URL even when the tab
            // is hidden — captures exist for every provider, so "no capture" alone
            // wouldn't stop a PayNow order from showing the New-refund button.
            refundSupported: null,
            // Incremented per load; a late-arriving response for a stale token is ignored.
            loadToken: 0,
        };
    },

    computed: {
        captureRepository() {
            return this.repositoryFactory.create('order_transaction_capture');
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        order() {
            return Shopware.Store.get('swOrderDetail')?.order;
        },

        gatewayTransaction() {
            // Pick the provider OrderTransaction by gateway-id custom field. In split-payment
            // orders the provider transaction may not be the most recent one.
            return (this.order?.transactions ?? []).find(
                (t) => t?.customFields?.crehler_payment_gateway_id,
            ) ?? null;
        },

        refunds() {
            const refunds = this.capture?.refunds ?? [];

            return [...refunds].sort(
                (a, b) => new Date(b.createdAt) - new Date(a.createdAt),
            );
        },

        totalAmount() {
            return this.capture?.amount?.totalPrice ?? 0;
        },

        refundedAmount() {
            const sum = this.refunds
                .filter((r) => GATEWAY_CONFIRMED_STATES.includes(r.stateMachineState?.technicalName))
                .reduce((acc, r) => acc + (r.amount?.totalPrice ?? 0), 0);

            return Math.round(sum * 100) / 100;
        },

        remainingAmount() {
            return Math.round((this.totalAmount - this.refundedAmount) * 100) / 100;
        },

        currencyIsoCode() {
            return this.order?.currency ? this.order.currency.isoCode : 'EUR';
        },

        currentYear() {
            return new Date().getFullYear();
        },
    },

    watch: {
        gatewayTransaction: {
            immediate: true,
            handler(transaction) {
                if (transaction) {
                    this.loadRefundSupport();
                    this.loadCapture();
                } else {
                    // No provider transaction (e.g. switched to another order) — drop the
                    // stale capture so the UI never shows a previous order's refunds.
                    this.capture = null;
                    this.refundSupported = null;
                }
            },
        },
    },

    methods: {
        loadRefundSupport() {
            const orderId = this.order?.id;

            if (!orderId) {
                this.refundSupported = null;

                return Promise.resolve();
            }

            return this.CrPaymentRefundService.isRefundSupported(orderId)
                .then((supported) => {
                    this.refundSupported = supported;
                })
                .catch(() => {
                    // Fail closed: treat an unverifiable provider as unsupported.
                    this.refundSupported = false;
                });
        },

        loadCapture() {
            if (!this.gatewayTransaction) {
                this.capture = null;

                return Promise.resolve();
            }

            this.isLoading = true;
            this.hasError = false;
            const token = ++this.loadToken;

            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('orderTransactionId', this.gatewayTransaction.id));
            criteria.addAssociation('refunds.stateMachineState');
            criteria.addAssociation('refunds.positions');

            return this.captureRepository
                .search(criteria, Shopware.Context.api)
                .then((result) => {
                    // Ignore a response that a newer load has already superseded.
                    if (token !== this.loadToken) {
                        return;
                    }

                    this.capture = result.first();
                })
                .catch(() => {
                    if (token === this.loadToken) {
                        this.hasError = true;
                    }
                })
                .finally(() => {
                    if (token === this.loadToken) {
                        this.isLoading = false;
                    }
                });
        },

        async onRefundCompleted() {
            // The refund and its order-transaction state transition are committed against
            // the LIVE order (the refund is saved with Shopware.Context.api), but this page
            // edits a draft version created on mount. A plain reloadEntityData() reloads that
            // stale draft, so the new payment status would not show on the "General" tab.
            // Branch a fresh draft from live first so every order tab reflects the change.
            await this.refreshOrderFromLive();

            return this.loadCapture();
        },

        async refreshOrderFromLive() {
            const store = Shopware.Store.get('swOrderDetail');
            const orderId = store?.order?.id ?? this.order?.id;

            if (store && orderId) {
                try {
                    // Reset to the live context and branch a new draft version from it — the
                    // committed refund/payment state lives on live, so the fresh version picks
                    // it up (mirrors sw-order-detail.createNewVersionId).
                    store.versionContext = Shopware.Context.api;
                    store.versionContext = await this.orderRepository.createVersion(orderId, store.versionContext);
                } catch (error) {
                    // Non-fatal — fall back to a plain reload below.
                }
            }

            if (this.swOrderDetailOnReloadEntityData) {
                // isSaved=false: the refund goes through the action endpoint, not the versioned
                // order entity, so there is nothing to save and no "unsaved changes" banner.
                await this.swOrderDetailOnReloadEntityData(false);
            }
        },
    },
});
