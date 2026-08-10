<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Infrastructure\Configuration;

use function array_map;

/**
 * Enum defining shared configuration fields added to all payment provider plugins.
 * These fields are automatically injected by ConfigurationServiceDecorator.
 */
enum BundleConfigField: string
{
    case EMBED_CARD_FORM = 'crPaymentEmbedCardForm';
    case BLIK_INPUT_POSITION = 'crPaymentBlikInputPosition';
    case TRANSACTION_DESCRIPTION = 'crPaymentTransactionDescription';
    case WAITING_TIME = 'crPaymentWaitingTime';
    /**
     * Lower bound for WAITING_TIME, in seconds. Below this the storefront would give up
     * before a normal pay-by-link confirmation has any chance to arrive.
     *
     * @var int
     */
    public const WAITING_TIME_MIN = 30;

    /**
     * Upper bound for WAITING_TIME, in seconds — past this the customer is just staring
     * at a spinner; the order is not lost either way, the gateway notification still books it.
     *
     * @var int
     */
    public const WAITING_TIME_MAX = 900;

    /**
     * Get the configuration element definition for Shopware admin.
     *
     * @param string $domain Full config domain (e.g., "CrehlerPayUPayment.config")
     *
     * @return array<string, mixed>
     */
    public function getElement(string $domain): array
    {
        $fullName = $domain . '.' . $this->value;

        return match ($this) {
            self::EMBED_CARD_FORM => [
                'name' => $fullName,
                'type' => 'bool',
                'config' => [
                    'label' => [
                        'en-GB' => 'Embed card form in checkout',
                        'de-DE' => 'Kartenformular im Checkout einbetten',
                        'pl-PL' => 'Osadź formularz karty w checkout',
                    ],
                    'helpText' => [
                        'en-GB' => 'When enabled, card payment form will be displayed directly in checkout instead of redirecting to payment provider',
                        'de-DE' => 'Wenn aktiviert, wird das Kartenformular direkt im Checkout angezeigt, anstatt zum Zahlungsanbieter weiterzuleiten',
                        'pl-PL' => 'Gdy włączone, formularz płatności kartą będzie wyświetlany bezpośrednio w checkout zamiast przekierowania do bramki płatności',
                    ],
                    'defaultValue' => false,
                ],
            ],
            self::BLIK_INPUT_POSITION => [
                'name' => $fullName,
                'type' => 'single-select',
                'config' => [
                    'label' => [
                        'en-GB' => 'BLIK code input position',
                        'de-DE' => 'Position der BLIK-Code-Eingabe',
                        'pl-PL' => 'Pozycja pola kodu BLIK',
                    ],
                    'helpText' => [
                        'en-GB' => 'Choose where the BLIK code input should be displayed',
                        'de-DE' => 'Wählen Sie, wo die BLIK-Code-Eingabe angezeigt werden soll',
                        'pl-PL' => 'Wybierz gdzie ma być wyświetlane pole do wprowadzenia kodu BLIK',
                    ],
                    'defaultValue' => 'checkout',
                    'options' => [
                        [
                            'id' => 'checkout',
                            'name' => [
                                'en-GB' => 'On checkout confirmation page',
                                'de-DE' => 'Auf der Checkout-Bestätigungsseite',
                                'pl-PL' => 'Na stronie potwierdzenia zamówienia',
                            ],
                        ],
                        [
                            'id' => 'separate',
                            'name' => [
                                'en-GB' => 'On separate page after order',
                                'de-DE' => 'Auf separater Seite nach der Bestellung',
                                'pl-PL' => 'Na osobnej stronie po złożeniu zamówienia',
                            ],
                        ],
                        [
                            'id' => 'hidden',
                            'name' => [
                                'en-GB' => 'Hidden (redirect to provider)',
                                'de-DE' => 'Ausgeblendet (Weiterleitung zum Anbieter)',
                                'pl-PL' => 'Ukryte (przekierowanie do bramki)',
                            ],
                        ],
                    ],
                ],
            ],
            self::TRANSACTION_DESCRIPTION => [
                'name' => $fullName,
                'type' => 'text',
                'config' => [
                    'label' => [
                        'en-GB' => 'Transaction description (sent to gateway)',
                        'de-DE' => 'Transaktionsbeschreibung (an Gateway gesendet)',
                        'pl-PL' => 'Opis transakcji (wysyłany do bramki)',
                    ],
                    'helpText' => [
                        'en-GB' => 'Human-readable description shown on the gateway. Available tokens: {{ orderNumber }}, {{ customerName }}, {{ salesChannelName }}.',
                        'de-DE' => 'Lesbare Beschreibung, die im Gateway angezeigt wird. Verfügbare Platzhalter: {{ orderNumber }}, {{ customerName }}, {{ salesChannelName }}.',
                        'pl-PL' => 'Czytelny opis pokazywany w bramce. Dostępne tokeny: {{ orderNumber }}, {{ customerName }}, {{ salesChannelName }}.',
                    ],
                    'defaultValue' => '{{ orderNumber }}',
                    'placeholder' => [
                        'en-GB' => '{{ orderNumber }}',
                        'de-DE' => '{{ orderNumber }}',
                        'pl-PL' => '{{ orderNumber }}',
                    ],
                ],
            ],
            self::WAITING_TIME => [
                'name' => $fullName,
                'type' => 'int',
                'config' => [
                    'label' => [
                        'en-GB' => 'Payment confirmation waiting time (seconds)',
                        'de-DE' => 'Wartezeit auf die Zahlungsbestätigung (Sekunden)',
                        'pl-PL' => 'Czas oczekiwania na potwierdzenie płatności (sekundy)',
                    ],
                    'helpText' => [
                        'en-GB' => 'How long the storefront waits for the gateway to confirm the payment before it stops polling and offers to change the payment method. Does not cancel the payment — a later confirmation still books the order.',
                        'de-DE' => 'Wie lange die Storefront auf die Zahlungsbestätigung des Gateways wartet, bevor sie das Polling beendet und einen Wechsel der Zahlungsart anbietet. Die Zahlung wird dadurch nicht storniert — eine spätere Bestätigung bucht die Bestellung weiterhin.',
                        'pl-PL' => 'Ile czasu storefront czeka na potwierdzenie płatności z bramki, zanim przerwie odpytywanie i zaproponuje zmianę metody płatności. Nie anuluje to płatności — późniejsze potwierdzenie i tak zaksięguje zamówienie.',
                    ],
                    'defaultValue' => self::WAITING_TIME->defaultValue(),
                    'min' => self::WAITING_TIME_MIN,
                    'max' => self::WAITING_TIME_MAX,
                ],
            ],
        };
    }

    /**
     * Default value seeded into system_config on plugin install (BundleConfigDefaultsInstaller)
     * and declared as defaultValue in the admin element. Kept here so both stay in sync.
     */
    public function defaultValue(): bool|int|string
    {
        return match ($this) {
            self::EMBED_CARD_FORM => false,
            self::BLIK_INPUT_POSITION => 'checkout',
            self::TRANSACTION_DESCRIPTION => '{{ orderNumber }}',
            // 120 s is what the storefront waited before this became configurable, so an
            // update changes nothing until an operator decides otherwise.
            self::WAITING_TIME => 120,
        };
    }

    /**
     * Get card title for the configuration section.
     *
     * @return array<string, string>
     */
    public static function getCardTitle(): array
    {
        return [
            'en-GB' => 'Display settings',
            'de-DE' => 'Anzeigeeinstellungen',
            'pl-PL' => 'Ustawienia wyświetlania',
        ];
    }

    /**
     * Get all configuration elements with full domain prefix.
     *
     * @param string $domain Full config domain (e.g., "CrehlerPayUPayment.config")
     *
     * @return array<array<string, mixed>>
     */
    public static function getAllElements(string $domain): array
    {
        return array_map(
            static fn (self $field) => $field->getElement($domain),
            self::cases()
        );
    }
}
