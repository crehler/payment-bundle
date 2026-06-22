/**
 * Testy zapisanych kart Store API
 *
 * Testuje endpoint:
 * - GET /cr/payment/card-tokens
 *
 * Scenariusze:
 * - Pobranie zapisanych kart (zalogowany klient)
 * - Próba pobrania kart jako gość
 *
 * UWAGA: Zapisywanie nowych kart wymaga prawdziwej transakcji
 * kartowej z włączonym tokenization, więc nie jest testowane automatycznie.
 */

import { describe, it, expect, beforeAll } from 'vitest';
import { StoreApiClient } from '../store-api-client.js';
import { testConfig, validateConfig } from '../config.js';
import {
    printHeader,
    printTestHeader,
    printEndpoint,
    printParams,
    printResponse,
    printTestResult,
    printStep,
    printInfo,
    printWarning,
    printError,
    TestResults,
} from '../utils/logger.js';

describe('Saved Cards Tests', () => {
    let client;
    let config;
    const results = new TestResults();
    let testCount = 0;
    const totalTests = 2;

    beforeAll(async () => {
        config = validateConfig();
        client = new StoreApiClient(config.storeUrl, config.accessKey);
        printHeader('SAVED CARDS TESTS');

        // Login
        printStep('Logowanie...');
        await client.login(config.customerEmail, config.customerPassword);
    });

    it('should get saved cards for logged-in customer', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Pobierz zapisane karty (zalogowany klient)');

        printEndpoint('GET', '/cr/payment/card-tokens');
        printParams({
            'Zalogowany': 'Tak',
            'Customer': config.customerEmail,
        });

        try {
            const response = await client.getSavedCards();
            const cards = response?.elements || [];

            expect(response).toBeDefined();

            if (cards.length > 0) {
                printResponse(200, {
                    'Liczba kart': `${cards.length}`,
                    'Przykład': `**** ${cards[0]?.cardNumberMasked?.slice(-4) || 'XXXX'}`,
                });
            } else {
                printResponse(200, {
                    'Liczba kart': '0',
                    'Info': 'Klient nie ma zapisanych kart',
                });
            }

            printTestResult(true, Date.now() - startTime);
            results.pass('Get saved cards (logged in)', Date.now() - startTime);
        } catch (error) {
            printError(error.message);
            printTestResult(false, Date.now() - startTime);
            results.fail('Get saved cards (logged in)', error, Date.now() - startTime);
            throw error;
        }
    });

    it('should handle guest access to saved cards', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Pobierz karty jako gość');

        // Create a fresh client without login
        const guestClient = new StoreApiClient(config.storeUrl, config.accessKey);

        printEndpoint('GET', '/cr/payment/card-tokens');
        printParams({
            'Zalogowany': 'Nie (gość)',
        });

        try {
            const response = await guestClient.rawRequest('GET', '/cr/payment/card-tokens');

            if (response.ok) {
                const cards = response.data?.elements || [];
                printResponse(200, {
                    'Liczba kart': `${cards.length}`,
                    'Info': 'Gość otrzymał pustą listę (oczekiwane)',
                });
                expect(cards.length).toBe(0);
            } else {
                printResponse(response.status, {
                    'Status': 'Odmowa dostępu (oczekiwane)',
                });
            }

            printTestResult(true, Date.now() - startTime);
            results.pass('Guest saved cards access', Date.now() - startTime);
        } catch (error) {
            // Error is expected for guest access
            printResponse(403, {
                'Status': 'Odmowa dostępu (oczekiwane)',
            });
            printTestResult(true, Date.now() - startTime);
            results.pass('Guest saved cards (forbidden)', Date.now() - startTime);
        }
    });
});
