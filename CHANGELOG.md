# Changelog

## 6.0.2

### Changed

- **Transition page no longer hardcodes provider logos / asset packages.** The payment
  transition page now exposes an overridable `cr_payment_transition_provider_logo` Twig
  block (default: a generic card icon). Each provider plugin renders its own logo from
  **its own** asset package by overriding the block — removing the bundle's coupling to
  provider plugin/bundle names, which broke the transition page after a provider plugin
  was renamed. Providers that ship such an override require `crehler/payment-bundle: >=6.0.2`.

## 6.0.1

### Changed

- **`CheckPaymentService` now treats the local transaction state as the source of
  truth.** The payment status is resolved from the Shopware order transaction first;
  the gateway is queried **only** on the final "reconcile" poll (after the storefront's
  wait window) when the transaction is still not `paid` locally — instead of on every poll.

### Added

- **`PaymentStatus::paidNotBooked()`** — new state for "paid at the gateway but not
  booked in the shop". When the wait window elapses and the gateway confirms payment
  while Shopware has not booked it, the storefront shows a "contact support" message
  instead of a generic timeout.
  - New `CheckPaymentStatusRequest::$reconcile` flag and `CheckPaymentStatusStruct::$mismatch`
    field on `POST /store-api/cr/payment/check`.
  - The `cr-check-payment-status` storefront poller sends `reconcile` after the wait
    window and renders the message; snippets `payment.checkPayment.mismatchText` /
    `mismatchSubtitle` (pl/en).
- **Compiled storefront assets are now shipped in the package** (like the admin build),
  so install / production is `composer install` + `assets:install` + `theme:compile`
  with **no Node** on the server. Storefront chunks use deterministic names
  (`webpackChunkName`), so the build is reproducible.

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