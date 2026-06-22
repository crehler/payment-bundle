import chalk from 'chalk';
import { exec } from 'child_process';
import { platform } from 'os';

/**
 * Test Reporter - jasna komunikacja co się dzieje w testach
 */

// Czy automatycznie otwierać URL w przeglądarce
const AUTO_OPEN_URLS = process.env.AUTO_OPEN_URLS !== 'false';

/**
 * Otwiera URL w domyślnej przeglądarce
 * Obsługuje macOS, Windows i Linux z fallbackami
 */
function openInBrowser(url) {
    const os = platform();

    // Komendy do otwierania URL w różnych systemach
    const commands = {
        darwin: ['open'],
        win32: ['cmd', '/c', 'start', '""'],
        linux: ['xdg-open', 'gnome-open', 'kde-open', 'wslview'],
    };

    const tryCommands = commands[os] || commands.linux;

    // Dla Windows - specjalna obsługa
    if (os === 'win32') {
        exec(`cmd /c start "" "${url}"`, (err) => {
            if (err) {
                console.log(chalk.gray(`  (Windows: nie udało się otworzyć przeglądarki)`));
            }
        });
        return;
    }

    // Dla macOS i Linux - próbuj kolejne komendy
    const tryOpen = (cmdIndex) => {
        if (cmdIndex >= tryCommands.length) {
            console.log(chalk.gray(`  (Nie udało się otworzyć przeglądarki - skopiuj URL ręcznie)`));
            return;
        }

        const cmd = tryCommands[cmdIndex];
        exec(`${cmd} "${url}"`, (err) => {
            if (err) {
                // Próbuj następną komendę
                tryOpen(cmdIndex + 1);
            }
        });
    };

    tryOpen(0);
}

const ICONS = {
    pass: '✓',
    fail: '✗',
    skip: '⊘',
    info: 'ℹ',
    step: '→',
    wait: '⏸',
    payment: '💳',
    order: '📦',
    redirect: '🔗',
    time: '⏱',
};

/**
 * Rysuje nagłówek sekcji testów
 */
export function printHeader(title) {
    const line = '═'.repeat(60);
    console.log(chalk.cyan(`\n${line}`));
    console.log(chalk.cyan.bold(`  ${title.toUpperCase()}`));
    console.log(chalk.cyan(`${line}\n`));
}

/**
 * Rysuje nagłówek pojedynczego testu
 */
export function printTestHeader(testNumber, totalTests, testName) {
    console.log(chalk.white.bold(`  [TEST ${testNumber}/${totalTests}] ${testName}`));
    console.log(chalk.gray(`  ${'─'.repeat(44)}`));
}

/**
 * Wyświetla informację o endpoincie
 */
export function printEndpoint(method, path) {
    console.log(chalk.gray(`  │ Endpoint:  ${chalk.yellow(method)} ${chalk.white(path)}`));
}

/**
 * Wyświetla parametry requestu
 */
export function printParams(params) {
    Object.entries(params).forEach(([key, value]) => {
        console.log(chalk.gray(`  │ ${key}: ${chalk.white(value)}`));
    });
}

/**
 * Wyświetla odpowiedź
 */
export function printResponse(status, details = {}) {
    console.log(chalk.gray(`  │`));
    console.log(chalk.gray(`  │ Response:`));
    console.log(chalk.gray(`  │   ├─ Status: ${status >= 200 && status < 300 ? chalk.green(status + ' OK') : chalk.red(status)}`));

    Object.entries(details).forEach(([key, value], index, arr) => {
        const isLast = index === arr.length - 1;
        const prefix = isLast ? '└─' : '├─';
        console.log(chalk.gray(`  │   ${prefix} ${key}: ${chalk.white(value)}`));
    });
}

/**
 * Wyświetla wynik testu
 */
export function printTestResult(passed, duration) {
    console.log(chalk.gray(`  │`));
    if (passed) {
        console.log(chalk.green(`  └─ Result: ${ICONS.pass} PASS`) + chalk.gray(` (${duration}ms)`));
    } else {
        console.log(chalk.red(`  └─ Result: ${ICONS.fail} FAIL`) + chalk.gray(` (${duration}ms)`));
    }
    console.log('');
}

/**
 * Wyświetla krok w teście
 */
export function printStep(message) {
    console.log(chalk.cyan(`  │ ${ICONS.step} ${message}`));
}

/**
 * Wyświetla informację
 */
export function printInfo(message) {
    console.log(chalk.blue(`  │ ${ICONS.info} ${message}`));
}

/**
 * Wyświetla ostrzeżenie
 */
export function printWarning(message) {
    console.log(chalk.yellow(`  │ ⚠ ${message}`));
}

/**
 * Wyświetla błąd
 */
export function printError(message) {
    console.log(chalk.red(`  │ ${ICONS.fail} ${message}`));
}

/**
 * Wyświetla nagłówek metody płatności
 */
export function printPaymentHeader(name, handlerType) {
    console.log(chalk.magenta.bold(`\n  ${ICONS.payment} [PAYMENT] ${name}`));
    if (handlerType) {
        console.log(chalk.gray(`     Handler: ${handlerType}`));
    }
}

/**
 * Wyświetla informację o zamówieniu
 */
export function printOrderCreated(orderNumber, orderId) {
    console.log(chalk.green(`  │ ${ICONS.order} Zamówienie: #${orderNumber}`));
    console.log(chalk.gray(`  │    ID: ${orderId}`));
}

/**
 * Wyświetla URL przekierowania i opcjonalnie otwiera w przeglądarce
 */
export function printRedirect(url, autoOpen = AUTO_OPEN_URLS) {
    const box = '═'.repeat(60);
    console.log('');
    console.log(chalk.green.bold(box));
    console.log(chalk.green.bold('  🔗 REDIRECT DO BRAMKI PŁATNOŚCI'));
    console.log(chalk.green.bold(box));
    console.log('');
    console.log(chalk.cyan.bold(`  ${url}`));
    console.log('');

    if (autoOpen) {
        console.log(chalk.yellow('  ▶ Otwieram w przeglądarce...'));
        openInBrowser(url);
    }

    console.log(chalk.green.bold(box));
    console.log('');
}

/**
 * Oczekiwanie na operatora
 */
export function printWaitForOperator(message) {
    console.log(chalk.yellow(`\n  ${ICONS.wait} ${message}`));
}

/**
 * Wyświetla podsumowanie testów
 */
export function printSummary(results) {
    const line = '═'.repeat(60);
    console.log(chalk.cyan(`\n${line}`));
    console.log(chalk.cyan.bold(`  PODSUMOWANIE TESTÓW`));
    console.log(chalk.cyan(`${line}\n`));

    const passed = results.filter(r => r.status === 'pass').length;
    const failed = results.filter(r => r.status === 'fail').length;
    const skipped = results.filter(r => r.status === 'skip').length;

    console.log(chalk.green(`  ${ICONS.pass} Zaliczone:  ${passed}`));
    console.log(chalk.red(`  ${ICONS.fail} Niezaliczone: ${failed}`));
    console.log(chalk.yellow(`  ${ICONS.skip} Pominięte:  ${skipped}`));
    console.log('');

    if (failed > 0) {
        console.log(chalk.red.bold(`  Niezaliczone testy:`));
        results.filter(r => r.status === 'fail').forEach(r => {
            console.log(chalk.red(`    - ${r.name}: ${r.error || 'Unknown error'}`));
        });
        console.log('');
    }

    console.log(chalk.cyan(`${line}\n`));

    return { passed, failed, skipped, total: results.length };
}

/**
 * Wyświetla czas wykonania
 */
export function printDuration(startTime) {
    const duration = Date.now() - startTime;
    const seconds = (duration / 1000).toFixed(2);
    console.log(chalk.gray(`\n  ${ICONS.time} Czas wykonania: ${seconds}s`));
}

/**
 * Klasa do zbierania wyników testów
 */
export class TestResults {
    #results = [];

    add(name, status, error = null, duration = 0) {
        this.#results.push({ name, status, error, duration, timestamp: new Date() });
    }

    pass(name, duration = 0) {
        this.add(name, 'pass', null, duration);
    }

    fail(name, error, duration = 0) {
        this.add(name, 'fail', error?.message || String(error), duration);
    }

    skip(name, reason) {
        this.add(name, 'skip', reason, 0);
    }

    getResults() {
        return [...this.#results];
    }

    print() {
        return printSummary(this.#results);
    }
}

export default {
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
    printPaymentHeader,
    printOrderCreated,
    printRedirect,
    printWaitForOperator,
    printSummary,
    printDuration,
    TestResults,
};
