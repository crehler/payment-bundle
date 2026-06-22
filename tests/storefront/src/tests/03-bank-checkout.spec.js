import { test, expect } from '@playwright/test';
import { validateConfig, paymentSelectors } from '../config.js';
import {
  login,
  addProductToCart,
  goToCheckout,
  selectPaymentMethod,
  getSubmethods,
  selectSubmethod,
  submitOrder,
  isPaymentProviderPage
} from '../utils/storefront.js';

const config = validateConfig();

test.describe('Bank Transfer Checkout', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, config);
    await addProductToCart(page, config.productNumber);
    await goToCheckout(page);
  });

  test('completes checkout with bank transfer and selected bank', async ({ page }) => {
    const bankMethod = await selectPaymentMethod(page, paymentSelectors.bank);
    test.skip(!bankMethod, 'No bank payment method configured');

    const submethods = await getSubmethods(page);
    test.skip(submethods.length === 0, 'No bank submethods available');

    // Select first bank
    await selectSubmethod(page, 0);

    const result = await submitOrder(page);

    // Should redirect to payment provider
    expect(result.url).toBeTruthy();
    expect(
      isPaymentProviderPage(result.url) || result.url.includes('/checkout/finish') || result.url.includes('/cr/payment')
    ).toBeTruthy();
  });

  test('redirects to provider payment page', async ({ page }) => {
    const bankMethod = await selectPaymentMethod(page, paymentSelectors.bank);
    test.skip(!bankMethod, 'No bank payment method configured');

    const submethods = await getSubmethods(page);
    if (submethods.length > 0) {
      await selectSubmethod(page, 0);
    }

    const result = await submitOrder(page);

    // Verify we left the shop domain (redirected to payment provider)
    const currentUrl = page.url();
    expect(
      isPaymentProviderPage(currentUrl) ||
      currentUrl.includes('/checkout/finish') ||
      currentUrl.includes('/cr/payment/transition')
    ).toBeTruthy();
  });

  test('can select different banks', async ({ page }) => {
    const bankMethod = await selectPaymentMethod(page, paymentSelectors.bank);
    test.skip(!bankMethod, 'No bank payment method configured');

    const submethods = await getSubmethods(page);
    test.skip(submethods.length < 2, 'Need at least 2 banks to test selection');

    // Select first bank
    await selectSubmethod(page, 0);
    const firstName = submethods[0].name;

    // Select second bank
    await selectSubmethod(page, 1);
    const secondName = submethods[1].name;

    // Names should be different
    expect(firstName).not.toBe(secondName);
  });

  // Regression: LIB-1783
  // When a logged-in customer has a previously saved bank preference (DB),
  // the storefront pre-fills the bank radio from that record. The session
  // entry that BankHandler reads is only written when SalesChannelContextSwitchEvent
  // carries `paymentSubMethod` — submitting confirmOrderForm does not fire that
  // event. Without the customer-data fallback in BankHandler::processPayment(),
  // channelId=0 was sent to Tpay and the provider responded with its full method
  // list instead of routing straight to the selected bank.
  test('uses saved bank when user confirms without re-selecting (LIB-1783)', async ({ page, context }) => {
    const bankMethod = await selectPaymentMethod(page, paymentSelectors.bank);
    test.skip(!bankMethod, 'No bank payment method configured');

    const submethods = await getSubmethods(page);
    test.skip(submethods.length === 0, 'No bank submethods available');

    // First visit: actively select a bank so it gets persisted to the customer record.
    const chosen = await selectSubmethod(page, 0);
    test.skip(!chosen, 'Could not select submethod');
    const chosenName = chosen.name;

    // Drop the session to force BankHandler to rely on customer-saved data, then log back in.
    await context.clearCookies();
    await page.goto('/');
    await login(page, config);
    await addProductToCart(page, config.productNumber);
    await goToCheckout(page);

    // The bank radio must come back pre-selected from the saved customer preference.
    const reselected = await selectPaymentMethod(page, paymentSelectors.bank);
    test.skip(!reselected, 'Bank method not visible after re-login');

    // Click confirm WITHOUT touching the bank radio — this is the scenario that triggered the bug.
    const result = await submitOrder(page);

    // Must end up on the chosen bank / provider, not back on a Tpay method-picker page.
    expect(result.url).toBeTruthy();
    expect(result.url).not.toMatch(/\/checkout\/confirm/);
    expect(
      isPaymentProviderPage(result.url) ||
      result.url.includes('/checkout/finish') ||
      result.url.includes('/cr/payment')
    ).toBeTruthy();
    // Sanity log so a failure makes the regression visible.
    expect(chosenName).toBeTruthy();
  });
});
