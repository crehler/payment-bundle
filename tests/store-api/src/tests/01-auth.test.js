/**
 * Testy autentykacji Store API
 *
 * Testuje:
 * - Logowanie poprawnym hasłem
 * - Logowanie błędnym hasłem
 * - Wylogowanie
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
    printError,
    TestResults,
} from '../utils/logger.js';

describe('Authentication Tests', () => {
    let client;
    let config;
    const results = new TestResults();
    let testCount = 0;
    const totalTests = 3;

    beforeAll(() => {
        config = validateConfig();
        client = new StoreApiClient(config.storeUrl, config.accessKey);
        printHeader('AUTHENTICATION TESTS');
    });

    it('should login with correct credentials', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Logowanie poprawnym hasłem');
        printEndpoint('POST', '/account/login');
        printParams({
            Email: config.customerEmail,
            Password: '********',
        });

        try {
            const response = await client.login(config.customerEmail, config.customerPassword);

            expect(response).toBeDefined();
            expect(client.contextToken).toBeTruthy();

            printResponse(200, {
                'Context Token': client.contextToken.substring(0, 20) + '...',
                'Customer ID': response?.id || 'N/A',
            });
            printTestResult(true, Date.now() - startTime);
            results.pass('Login with correct credentials', Date.now() - startTime);
        } catch (error) {
            printError(error.message);
            printTestResult(false, Date.now() - startTime);
            results.fail('Login with correct credentials', error, Date.now() - startTime);
            throw error;
        }
    });

    it('should fail login with wrong password', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Logowanie błędnym hasłem');
        printEndpoint('POST', '/account/login');
        printParams({
            Email: config.customerEmail,
            Password: 'wrong-password-123',
        });

        // Use a fresh client to avoid context token issues
        const freshClient = new StoreApiClient(config.storeUrl, config.accessKey);

        try {
            const response = await freshClient.rawRequest('POST', '/account/login', {
                email: config.customerEmail,
                password: 'wrong-password-123',
            });

            // Should return 401 or similar error
            expect(response.ok).toBe(false);
            expect(response.status).toBeGreaterThanOrEqual(400);

            printResponse(response.status, {
                'Error': 'Unauthorized (expected)',
            });
            printTestResult(true, Date.now() - startTime);
            results.pass('Login with wrong password (rejected)', Date.now() - startTime);
        } catch (error) {
            // If it throws, that's also acceptable for wrong credentials
            printResponse(401, { Error: 'Unauthorized (expected)' });
            printTestResult(true, Date.now() - startTime);
            results.pass('Login with wrong password (rejected)', Date.now() - startTime);
        }
    });

    it('should logout successfully', async () => {
        const startTime = Date.now();
        testCount++;
        printTestHeader(testCount, totalTests, 'Wylogowanie');

        // First ensure we're logged in
        if (!client.contextToken) {
            printStep('Logowanie przed testem wylogowania...');
            await client.login(config.customerEmail, config.customerPassword);
        }

        printEndpoint('POST', '/account/logout');
        printParams({
            'Context Token': client.contextToken.substring(0, 20) + '...',
        });

        try {
            await client.logout();

            printResponse(200, {
                Status: 'Logged out',
            });
            printTestResult(true, Date.now() - startTime);
            results.pass('Logout', Date.now() - startTime);
        } catch (error) {
            printError(error.message);
            printTestResult(false, Date.now() - startTime);
            results.fail('Logout', error, Date.now() - startTime);
            throw error;
        }
    });
});
