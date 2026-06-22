import { expect } from '@playwright/test';

/**
 * Accept cookie banner if visible
 */
export async function acceptCookies(page) {
  // Remove Symfony debug toolbar
  await page.evaluate(() => {
    document.querySelectorAll('[id^="sfwdt"], .sf-toolbar').forEach((el) => el.remove());
  }).catch(() => {});

  const selectors = [
    '[data-cookie-permission-accept]',
    '.cookie-permission-button',
    'button:has-text("Akceptuj")',
    'button:has-text("Accept")',
    '.js-cookie-accept-all-button'
  ];

  for (const selector of selectors) {
    const btn = page.locator(selector).first();
    if (await btn.isVisible({ timeout: 1000 }).catch(() => false)) {
      await btn.click({ force: true });
      await page.waitForTimeout(500);
      return;
    }
  }
}

/**
 * Login to customer account
 */
export async function login(page, config) {
  // First check if already logged in
  await page.goto('/account');
  await acceptCookies(page);

  // If we're on the account page (not login), we're already logged in
  if (!page.url().includes('/account/login')) {
    return;
  }

  // Use specific login form selectors (not registration form)
  const loginForm = page.locator('form.login-form, form[action*="/account/login"]').first();
  await loginForm.locator('#loginMail, input[name="email"]').first().fill(config.customerEmail);
  await loginForm.locator('#loginPassword, input[name="password"]').first().fill(config.customerPassword);
  await loginForm.locator('button[type="submit"]').click();

  // Wait for redirect to account page
  await page.waitForURL(/\/account(?!\/login)/, { timeout: 15000 });
}

/**
 * Add product to cart by product number
 */
export async function addProductToCart(page, productNumber) {
  await page.goto(`/search?search=${encodeURIComponent(productNumber)}`);
  await acceptCookies(page);

  // Click product in search results
  const productLink = page.locator('.product-box a.product-name, .product-name a').first();
  await expect(productLink).toBeVisible({ timeout: 10000 });
  await productLink.click();

  // Add to cart
  await page.waitForLoadState('domcontentloaded');
  const buyButton = page.locator('.btn-buy, [data-add-to-cart]').first();
  await expect(buyButton).toBeVisible();
  await buyButton.click();

  // Wait for cart confirmation (alert or offcanvas)
  const cartConfirmation = page.locator('.alert-success, .offcanvas.is-open, .offcanvas-cart').first();
  await expect(cartConfirmation).toBeVisible({ timeout: 10000 });

  // Close offcanvas cart if open
  const closeBtn = page.locator('.offcanvas .btn-close, .offcanvas-close').first();
  if (await closeBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
    await closeBtn.click();
    await page.waitForTimeout(500);
  }
}

/**
 * Navigate to checkout confirm page
 */
export async function goToCheckout(page) {
  await page.goto('/checkout/confirm');
  await page.waitForLoadState('networkidle');

  // Wait for payment methods to be visible (main indicator of checkout page)
  await expect(page.locator('.payment-method').first()).toBeVisible({ timeout: 15000 });
}

/**
 * Select payment method by data attribute selector
 * @param {Page} page - Playwright page
 * @param {string} selector - CSS selector (e.g., '[data-cr-bank="true"]')
 * @returns {Promise<{name: string, id: string, element: Locator} | null>}
 */
export async function selectPaymentMethod(page, selector) {
  const method = page.locator(selector).first();

  if (!(await method.isVisible({ timeout: 3000 }).catch(() => false))) {
    return null;
  }

  const radio = method.locator('input[type="radio"]').first();
  const label = method.locator('.payment-method-label, label').first();
  const name = await label.innerText().catch(() => '');
  const id = await radio.getAttribute('value').catch(() => null);

  await radio.check({ force: true });
  await page.waitForLoadState('networkidle');

  return { name: name.trim(), id, element: method };
}

/**
 * Get all payment methods matching selector
 * @param {Page} page - Playwright page
 * @param {string} selector - CSS selector
 * @returns {Promise<Array<{name: string, id: string, element: Locator}>>}
 */
export async function getPaymentMethods(page, selector = '.payment-method') {
  const methods = [];
  const items = page.locator(selector);
  const count = await items.count();

  for (let i = 0; i < count; i++) {
    const item = items.nth(i);
    const label = item.locator('.payment-method-label, label').first();
    const name = await label.innerText().catch(() => '');
    const radio = item.locator('input[type="radio"]').first();
    const id = await radio.getAttribute('value').catch(() => null);

    methods.push({ name: name.trim(), id, element: item });
  }

  return methods;
}

/**
 * Get submethods list (banks) for current payment method
 * Supports multiple HTML structures:
 * - CrehlerPaymentBundle: .payment-sub-methods > .payment-sub-method
 * - PayU inline: payment method container with multiple radios
 */
export async function getSubmethods(page) {
  const submethods = [];

  // Try CrehlerPaymentBundle structure first
  const crContainer = page.locator('.payment-sub-methods, .cr-bank-selector');
  if (await crContainer.isVisible({ timeout: 2000 }).catch(() => false)) {
    const items = crContainer.locator('.payment-sub-method, .cr-bank-selector-item');
    const count = await items.count();

    for (let i = 0; i < count; i++) {
      const item = items.nth(i);
      const label = item.locator('.payment-sub-method-label-name, .cr-bank-selector-item__name, label').first();
      const name = await label.innerText().catch(() => '');
      const input = item.locator('input[type="radio"]').first();
      submethods.push({ name: name.trim(), element: item, input });
    }
    if (submethods.length > 0) return submethods;
  }

  // Try PayU/generic inline structure (radios inside selected payment method)
  const activeMethod = page.locator('.payment-method.is-active, [data-cr-payment].is-active, .cr-payment-wrapper:has(input[type="radio"]:checked)').first();
  if (await activeMethod.isVisible({ timeout: 1000 }).catch(() => false)) {
    // Look for submethod radios (not the main payment method radio)
    const subRadios = activeMethod.locator('input[type="radio"][name="paymentSubMethod"], .payment-sub-method input[type="radio"]');
    const count = await subRadios.count();

    for (let i = 0; i < count; i++) {
      const input = subRadios.nth(i);
      const container = input.locator('..').first();
      const img = container.locator('img').first();
      const name = await img.getAttribute('alt').catch(() => '') || '';
      submethods.push({ name: name.trim(), element: container, input });
    }
  }

  return submethods;
}

/**
 * Select submethod by index
 */
export async function selectSubmethod(page, index = 0) {
  // Expand submethod list if collapsed
  const toggle = page.locator('.cr-bank-selector-selected__change, .payment-sub-methods-selected-change').first();
  if (await toggle.isVisible().catch(() => false)) {
    await toggle.click();
    await page.waitForTimeout(300);
  }

  const submethods = await getSubmethods(page);
  if (submethods.length <= index) {
    return null;
  }

  await submethods[index].input.check({ force: true });
  await page.waitForTimeout(500);

  return submethods[index];
}

/**
 * Get currently selected submethod name
 */
export async function getSelectedSubmethodName(page) {
  const selectors = [
    '.cr-bank-selector-selected__name',
    '.payment-sub-methods-selected-name',
    '.cr-bank-selector-item.is-selected .cr-bank-selector-item__name',
    '.payment-sub-method.is-selected .payment-sub-method-label-name'
  ];

  for (const selector of selectors) {
    const el = page.locator(selector).first();
    if (await el.isVisible().catch(() => false)) {
      return (await el.innerText()).trim();
    }
  }

  return null;
}

/**
 * Fill BLIK code input
 */
export async function fillBlikCode(page, code) {
  const input = page.locator('#crehlerBlikCode, input[name="blikCode"], .blik-code-input input').first();
  if (await input.isVisible().catch(() => false)) {
    await input.fill(code);
    return true;
  }
  return false;
}

/**
 * Submit order and wait for redirect
 */
export async function submitOrder(page) {
  const submitBtn = page.locator('#confirmFormSubmit, button[type="submit"]').filter({ hasText: /zamów|kup|order|buy|bestellen/i }).first();
  await expect(submitBtn).toBeVisible();

  // Accept terms if checkbox exists and is not checked
  const termsCheckbox = page.locator('#tos, input[name="tos"]').first();
  if (await termsCheckbox.isVisible().catch(() => false)) {
    const isChecked = await termsCheckbox.isChecked();
    if (!isChecked) {
      await termsCheckbox.check({ force: true });
    }
  }

  await submitBtn.click();

  // Wait for navigation (either to payment provider or finish page)
  await page.waitForURL((url) => {
    const path = url.pathname;
    return !path.includes('/checkout/confirm');
  }, { timeout: 30000 });

  return {
    url: page.url(),
    isExternalRedirect: !page.url().includes(process.env.STORE_URL || '')
  };
}

/**
 * Check if we're on payment provider page (external redirect)
 */
export function isPaymentProviderPage(url) {
  const providerDomains = [
    'payu.com',
    'przelewy24.pl',
    'pay.autopay.eu',
    'secure.paynow.pl',
    'blik.',
    'sandbox'
  ];

  return providerDomains.some((domain) => url.includes(domain));
}
