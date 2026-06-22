# Changelog

## 6.0.0

Major release consolidating duplicated provider logic into the shared bundle.
New base classes and services — providers now implement only gateway-specific
pieces. Plugins must require `crehler/payment-bundle: ^6.0`.

### Added

- `AbstractPaymentMethodHandler::payViaBlikAuthorize()` — shared layer-zero BLIK pay() flow.
- `AbstractPaymentMethodHandler::buildPaymentUrls()` / `persistGatewayPaymentId()` helpers.
  The base handler constructor now also requires `OrderTransactionRepositoryInterface`.
- `AbstractPaymentNotificationSubscriber` + `TransactionStateApplier` + `TransactionStateTransition`
  enum — providers implement only `supports()`/`verify()`/`resolveOrderTransaction()`/`mapStatus()`.
- `AbstractGatewayClientFactory` (+ `GatewayConfigurationException`) — shared credential/sandbox reading.
- `AbstractPaymentSubMethodProvider` (+ `RawSubMethod`) — shared min/max filtering and mapping.
- `PaymentRequestDtoFactory::reconcileItemsTotal()` — shared item-total reconciliation.
- Shared `StoredCard` entity (`crehler_payment_stored_card`) + `StoredCardService` with migration
  copying legacy `crehler_tpay_saved_card` and `paynow_customer_card_token` rows.
- Shared `cr-saved-card-selector` storefront plugin.
- `Domain\Constant\PaymentCustomFields::GATEWAY_PAYMENT_ID`.

## Unreleased

### Changed — **BREAKING** — `PaymentGatewayStatusProviderInterface` signature (LIB-1782)

`PaymentGatewayStatusProviderInterface::getPaymentStatus()` now returns the canonical
`Crehler\PaymentBundle\Domain\ValueObjects\PaymentStatus` value object (or `null`) instead
of `GatewayPaymentStatus`. The `GatewayPaymentStatus` VO has been removed entirely.

**Why:** the removed VO carried PayNow-era uppercase status constants (`CONFIRMED`,
`PENDING`, `REJECTED`, ...) that did not match what Tpay (and other gateways) return
from their REST APIs. As a result the mapping always fell through to `failed()` and
`/store-api/cr/payment/check` returned `status:false, waiting:false` even for paid orders.

**Migration for plugins implementing `PaymentGatewayStatusProviderInterface`:**

Before:

```php
public function getPaymentStatus(OrderTransactionEntity $orderTransaction): ?GatewayPaymentStatus
{
    // ...
    return new GatewayPaymentStatus(status: $rawGatewayStatus, gatewayPaymentId: $id);
}
```

After (each provider owns its mapping):

```php
public function getPaymentStatus(OrderTransactionEntity $orderTransaction): ?PaymentStatus
{
    // ...
    return match ($rawGatewayStatus) {
        'paid', 'correct'   => PaymentStatus::paid(),
        'pending', 'new'    => PaymentStatus::waiting(),
        'error', 'failed'   => PaymentStatus::failed(),
        default             => null, // fall back to Shopware state machine
    };
}
```

Returning `null` defers the decision to the Shopware order-transaction state machine.
Use it for gateway statuses that do not map cleanly to paid/waiting/failed (e.g. `chargeback`,
`refund`, or unknown values).

See `docs/providers/how-to-add-provider.md` §8 for the full guide.
