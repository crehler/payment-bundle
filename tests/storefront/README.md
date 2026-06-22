# Storefront Payment E2E Tests

Testy E2E płatności w storefront dla CrehlerPaymentBundle (Playwright).

## Wymagania

- Node.js 18+
- Skonfigurowany klient testowy w Shopware
- Produkt testowy w kanale sprzedaży
- Aktywne metody płatności (bank, BLIK)

## Instalacja

```bash
npm install
npx playwright install chromium
```

## Konfiguracja

```bash
cp .env.example .env
```

Uzupełnij `.env`:

```ini
STORE_URL=https://your-shop.example.com
TEST_PRODUCT_NUMBER=SW10001
TEST_CUSTOMER_EMAIL=test@example.com
TEST_CUSTOMER_PASSWORD=shopware
BLIK_TEST_CODE=777123
```

## Uruchomienie

```bash
# Wszystkie testy
npm test

# Konkretny test
npm test -- src/tests/01-submethods.spec.js

# Z widoczną przeglądarką
npm run test:headed

# Tryb debug
npm run test:debug

# Tryb UI
npm run test:ui
```

## Testy

| Plik | Opis |
|------|------|
| `01-submethods.spec.js` | Wybór submetod (banków) w checkout |
| `02-blik-checkout.spec.js` | Checkout z płatnością BLIK |
| `03-bank-checkout.spec.js` | Checkout z przelewem bankowym |

## Struktura

```
tests/storefront/
├── playwright.config.js
├── package.json
├── .env.example
└── src/
    ├── config.js
    ├── utils/
    │   └── storefront.js
    └── tests/
        ├── 01-submethods.spec.js
        ├── 02-blik-checkout.spec.js
        └── 03-bank-checkout.spec.js
```

## Artefakty

Przy błędach Playwright zapisuje:
- Screenshots: `test-results/*/`
- Video: `test-results/*/`
- Trace: `test-results/*/trace.zip`

Podgląd trace:
```bash
npx playwright show-trace test-results/*/trace.zip
```
