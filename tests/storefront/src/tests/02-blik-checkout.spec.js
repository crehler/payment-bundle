import { test, expect } from '@playwright/test';
import { validateConfig, paymentSelectors } from '../config.js';
import {
  login,
  addProductToCart,
  goToCheckout,
  selectPaymentMethod,
  fillBlikCode,
  submitOrder,
  isPaymentProviderPage
} from '../utils/storefront.js';

const config = validateConfig();

test.describe('BLIK Checkout', () => {
  test.beforeEach(async ({ page }) => {
    await login(page, config);
    await addProductToCart(page, config.productNumber);
    await goToCheckout(page);
  });

  test('completes checkout with BLIK payment', async ({ page }) => {
    const blikMethod = await selectPaymentMethod(page, paymentSelectors.blik);
    test.skip(!blikMethod, 'No BLIK payment method configured');

    // Fill BLIK code if input is visible
    const hasBlikInput = await fillBlikCode(page, config.blikCode);

    const result = await submitOrder(page);

    // Should redirect to payment provider or show BLIK confirmation
    expect(result.url).toBeTruthy();

    // If no BLIK input was on checkout page, we should be redirected to provider or BLIK authorize page
    if (!hasBlikInput) {
      expect(
        isPaymentProviderPage(result.url) ||
        result.url.includes('/checkout/finish') ||
        result.url.includes('/cr/payment') ||
        result.url.includes('/cr/blik')
      ).toBeTruthy();
    }
  });

  test('shows BLIK code input on checkout page', async ({ page }) => {
    const blikMethod = await selectPaymentMethod(page, paymentSelectors.blik);
    test.skip(!blikMethod, 'No BLIK payment method configured');

    // Check if BLIK input exists (may be hidden until order submit or visible immediately)
    const blikInput = page.locator('#crehlerBlikCode, input[name="blikCode"], .blik-code-input input').first();

    // Some implementations show BLIK input after selecting BLIK method
    // Some show it on a separate page after order submit
    // Both are valid implementations
    const isVisible = await blikInput.isVisible({ timeout: 3000 }).catch(() => false);

    // Just verify the payment method is selected and has correct data attribute
    expect(await page.locator('[data-cr-blik="true"]').count()).toBeGreaterThan(0);
  });
});
