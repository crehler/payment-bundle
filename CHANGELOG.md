# Changelog

## Unreleased

### Added

- **`{{ orderId }}` and `{{ orderIds }}` tokens in the transaction description.** Integrations that
  read the DAL key orders by their `_uniqueIdentifier`, which gateways never saw because the
  description only carried the order number — leaving no shared key between a payment and the
  order it belongs to. `{{ orderIds }}` resolves to that same single id until a plugin extends it
  into a list of related orders.

  **Strictly opt-in — the default description is unchanged.** The field's `defaultValue` and the
  renderer's fallback both stay `{{ orderNumber }}`, and the defaults installer never overwrites a
  value an operator already set, so no shop starts sending an order id to its gateway until someone
  puts the token into the template.
- **`TransactionDescriptionTokensEvent`** — extension point letting a plugin overwrite the resolved
  tokens. The bundle only knows a single order; a wider grouping (split deliveries, for example) is
  owned by the plugin implementing it. Listeners should guard on `usesToken()` so a token the
  template never mentions costs nothing.
- **Payment-confirmation waiting time is configurable again.** How long the storefront
  polls for the gateway confirmation before it stops and offers to change the payment
  method was hardcoded at 120 s in two places. It is now the shared
  `{PluginName}.config.crPaymentWaitingTime` field (seconds, default 120, range 30–900)
  on the "Ustawienia wyświetlania" card, so it appears in every provider plugin without
  a change on their side. Read it through `PaymentBundleConfigService::getWaitingTimeMs()`,
  which resolves the owning plugin, applies the range and returns milliseconds.
  This is a new config field, so it needs a **minor** release.

### Fixed

- **The rendered description can no longer exceed the gateway's limit.** `render()` accepts an
  optional `$maxLengthBytes`; over-long output is cut with `mb_strcut()` and logged as a warning.
  Gateways validate this field with `strlen()`, so the limit counts bytes — Polish characters cost
  two of them. Previously a long template configured in the admin made the gateway reject every
  transaction on the sales channel.
- **Stray separators are stripped with a Unicode-aware pattern.** The separator set contains
  multibyte characters (en dash, em dash, middle dot), and `trim()` matches its charlist byte by
  byte — so a description ending in an unrelated multibyte character lost its last byte and reached
  the gateway as malformed UTF-8.

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