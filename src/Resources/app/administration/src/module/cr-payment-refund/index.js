import './extension/sw-order-detail';
import './page/cr-payment-refund-detail';
import './component/cr-payment-refund-shell';
import './component/cr-payment-refund-card';
import './component/cr-payment-refund-history';
import './component/cr-payment-refund-modal';

Shopware.Module.register('cr-payment-refund', {
    routeMiddleware(next, currentRoute) {
        const alreadyRegistered = (currentRoute.children ?? []).some(
            (child) => child.name === 'cr.payment.refund.detail',
        );

        if (currentRoute.name === 'sw.order.detail' && !alreadyRegistered) {
            currentRoute.children.push({
                name: 'cr.payment.refund.detail',
                path: '/sw/order/detail/:id/payment-refunds',
                component: 'cr-payment-refund-detail',
                meta: {
                    parentPath: 'sw.order.index',
                    privilege: 'order_refund.viewer',
                },
            });
        }

        next(currentRoute);
    },
});
