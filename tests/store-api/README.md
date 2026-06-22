# Store API Payment Integration Tests

Testy integracyjne płatności przez Shopware Store API dla CrehlerPaymentBundle.

## Wymagania wstępne

Przed uruchomieniem testów upewnij się, że:

1. **Testowy klient** istnieje w systemie z:
   - Poprawnym adresem email i hasłem
   - Skonfigurowanym domyślnym adresem rozliczeniowym
   - Skonfigurowanym domyślnym adresem wysyłki

2. **Testowy produkt** jest dostępny w kanale sprzedaży

3. **Metody płatności** są skonfigurowane i aktywne

Szczegółowa dokumentacja: [docs/testing.md](../../custom/static-plugins/CrehlerPaymentBundle/docs/testing.md)

## Instalacja

```bash
cd tests/store-api
npm install
```

## Konfiguracja

1. Skopiuj plik `.env.example` do `.env`:

```bash
cp .env.example .env
```

2. Wypełnij wartości w `.env`:

```env
# URL sklepu (bez końcowego slasha)
STORE_URL=https://your-shop.example.com

# Klucz dostępu kanału sprzedaży (nagłówek sw-access-key)
SW_ACCESS_KEY=SWSC...

# Numer produktu testowego
TEST_PRODUCT_NUMBER=SW10001

# Dane logowania testowego klienta
TEST_CUSTOMER_EMAIL=test@example.com
TEST_CUSTOMER_PASSWORD=shopware

# Kod BLIK do testów (6 cyfr)
BLIK_TEST_CODE=777123
```

## Uruchomienie testów

```bash
# Wszystkie testy
npm test

# Pojedynczy plik testowy
npm test -- src/tests/01-auth.test.js
npm test -- src/tests/02-submethods.test.js
npm test -- src/tests/06-blik.test.js

# Tryb watch
npm run test:watch

# Tryb interaktywny (pełny checkout z płatnością)
TEST_MODE=interactive npm test -- src/tests/07-full-checkout.test.js
```

## Struktura testów

```
tests/store-api/
├── package.json           # Zależności i skrypty
├── vitest.config.js       # Konfiguracja Vitest
├── .env.example           # Przykładowa konfiguracja
├── .env                   # Twoja konfiguracja (nie commituj!)
├── README.md              # Ta dokumentacja
└── src/
    ├── config.js              # Ładowanie konfiguracji
    ├── store-api-client.js    # Klient Store API
    │
    ├── tests/
    │   ├── 01-auth.test.js          # Autentykacja
    │   ├── 02-submethods.test.js    # Submetody płatności
    │   ├── 03-customer-prefs.test.js# Preferencje klienta
    │   ├── 04-payment-status.test.js# Status płatności
    │   ├── 05-saved-cards.test.js   # Zapisane karty
    │   ├── 06-blik.test.js          # Płatności BLIK
    │   └── 07-full-checkout.test.js # Pełny checkout
    │
    └── utils/
        └── logger.js          # Formatowanie wyników
```

## Pokrycie testów

### Endpointy CrehlerPaymentBundle

| Endpoint | Plik testowy |
|----------|--------------|
| `GET /cr/payment-sub-methods/{id}` | 02-submethods.test.js |
| `GET /cr/payment-sub-methods` | 02-submethods.test.js |
| `POST /cr/payment/check` | 04-payment-status.test.js |
| `POST /cr/payment/blik` | 06-blik.test.js |
| `GET /customer/cr/payment-sub-method` | 03-customer-prefs.test.js |
| `PATCH /customer/cr/payment-sub-method` | 03-customer-prefs.test.js |
| `GET /cr/payment/card-tokens` | 05-saved-cards.test.js |

### Scenariusze testowe

- **01-auth**: Logowanie, błędne hasło, wylogowanie
- **02-submethods**: Pobieranie submetod, filtrowanie po kwocie
- **03-customer-prefs**: Zapisywanie/pobieranie preferencji, walidacja
- **04-payment-status**: Status zamówienia, nieistniejące ID, dostęp gościa
- **05-saved-cards**: Lista zapisanych kart, dostęp bez logowania
- **06-blik**: Poprawny kod, błędny format, brak kodu, zła metoda
- **07-full-checkout**: Pełny checkout dla wszystkich metod płatności

## Przykładowy output

```
════════════════════════════════════════════════════════════
  BLIK PAYMENT TESTS
════════════════════════════════════════════════════════════

  ⚙ Logowanie...
  ⚙ Wyszukiwanie produktu testowego...
  ℹ Produkt: Test Product
  ⚙ Wyszukiwanie metody BLIK...
  ℹ Metoda BLIK: PayU - BLIK

  [TEST 1/4] Płatność BLIK z poprawnym kodem
  ──────────────────────────────────────────────
  │ ⚙ Przygotowanie koszyka...
  │ ⚙ Tworzenie zamówienia...
  │ 📦 Zamówienie: #10042 (uuid-xxx)
  │
  │ Endpoint: POST /cr/payment/blik
  │ Payment Method ID: abc-123
  │ BLIK Code: 777123
  │
  │ Response:
  │   Status: 200
  │   Success: TAK
  │
  └─ ✓ PASS (1234ms)
```

## Interakcja operatora

W trybie interaktywnym (`TEST_MODE=interactive`), gdy test wymaga dokończenia płatności:

1. Test wyświetli URL do bramki płatności
2. Otwórz URL w przeglądarce
3. Dokończ płatność (lub anuluj jeśli to test)
4. Wróć do terminala i naciśnij Enter

## Troubleshooting

### "Invalid credentials"

Sprawdź email i hasło klienta testowego w `.env`.

### "Product with number X not found"

Produkt nie istnieje lub nie jest dostępny w kanale sprzedaży.

### "No shipping method available"

Klient musi mieć kompletny adres wysyłkowy z krajem obsługiwanym przez metodę wysyłki.

### "Store API Error 401"

Niepoprawny `SW_ACCESS_KEY`. Sprawdź czy klucz jest poprawny dla danego kanału sprzedaży.

### BLIK "Invalid code"

Kod BLIK musi mieć 6 cyfr. W środowisku sandbox kod testowy może nie działać.
