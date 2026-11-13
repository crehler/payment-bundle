<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Application\Service;

use Crehler\PaymentBundle\Infrastructure\Struct\CrehlerLineItemPrice;
use Crehler\PaymentBundle\Shared\AmountFormat;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\{CalculatedTax, CalculatedTaxCollection};

use function array_key_exists;
use function round;

final readonly class CartChanger
{
    public function __construct(
        private AmountFormat $amountFormat,
    ) {
    }

    public function change(Cart $cart): Cart
    {
        foreach ($cart->getLineItems() as $lineItem) {
            if (
                $lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE
                && $lineItem->getType() !== 'crehlerbundle'
                && $lineItem->getType() !== 'crehlerbundleitem'
            ) {
                continue;
            }

            $lineItem->addExtension(
                CrehlerLineItemPrice::getExtensionName(),
                $this->createCrehlerPriceFormCalculatedPrice($lineItem->getPrice())
            );

            if ($lineItem->hasChildren()) {
                foreach ($lineItem->getChildren() as $child) {
                    if (
                        $child->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE
                        && $child->getType() !== 'crehlerbundle'
                        && $child->getType() !== 'crehlerbundleitem'
                    ) {
                        continue;
                    }

                    $child->addExtension(
                        CrehlerLineItemPrice::getExtensionName(),
                        $this->createCrehlerPriceFormCalculatedPrice(
                            new CalculatedPrice(
                                $child->getPrice()?->getUnitPrice(),
                                $child->getPrice()?->getUnitPrice() * $child->getQuantity(),
                                $child->getPrice()?->getCalculatedTaxes(),
                                $child->getPrice()?->getTaxRules(),
                                $child->getQuantity(),
                                null,
                                null,
                                null
                            )
                        )
                    );
                }
            }
        }
        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== LineItem::PROMOTION_LINE_ITEM_TYPE) {
                continue;
            }

            $composition = $lineItem->getPayloadValue('composition');
            if ($composition === null) {
                continue;
            }

            foreach ($composition as $item) {
                if (!array_key_exists('id', $item) || !array_key_exists('quantity', $item) || !array_key_exists('discount', $item)) {
                    continue;
                }

                $lineItem = $cart->getLineItems()->get($item['id']);
                if ($lineItem === null) {
                    continue;
                }

                $currentCrehlerPrice = $lineItem->getExtension(CrehlerLineItemPrice::getExtensionName());
                if (!$currentCrehlerPrice instanceof CrehlerLineItemPrice) {
                    continue;
                }

                $currentCrehlerPrice = $this->recalculateCrehlerPrice($currentCrehlerPrice, $item['quantity'], $item['discount']);
                $lineItem->addExtension(CrehlerLineItemPrice::getExtensionName(), $currentCrehlerPrice);

                // Only children priced in the first pass take a share of the promotion. A Shopware 6.7
                // grouped bundle adds its discount as a `discount` child, already included in the parent price.
                $pricedChildren = $lineItem->getChildren()->filter(
                    static fn (LineItem $child): bool => $child->getExtension(CrehlerLineItemPrice::getExtensionName()) instanceof CrehlerLineItemPrice
                );
                if ($pricedChildren->count() === 0) {
                    continue;
                }

                $pennyCorrectionCounter = 0;
                foreach ($pricedChildren as $child) {
                    $childPriceParticipation = ($child->getQuantity() * $child->getPrice()->getUnitPrice()) / $lineItem->getPrice()->getTotalPrice();
                    $currentCrehlerChildPrice = $child->getExtension(CrehlerLineItemPrice::getExtensionName());
                    $currentCrehlerChildPrice = $this->recalculateCrehlerPrice($currentCrehlerChildPrice, $item['quantity'], $item['discount'] * $childPriceParticipation);
                    $pennyCorrectionCounter += $currentCrehlerChildPrice->getTotalPrice();
                    $child->addExtension(CrehlerLineItemPrice::getExtensionName(), $currentCrehlerChildPrice);
                }

                if ($pennyCorrectionCounter === $currentCrehlerPrice->getTotalPrice()) {
                    continue;
                }

                $pennyCorrection = $currentCrehlerPrice->getTotalPrice() - $pennyCorrectionCounter;
                $child = $pricedChildren->last();
                /** @var CrehlerLineItemPrice $currentCrehlerChildPrice */
                $currentCrehlerChildPrice = $child->getExtension(CrehlerLineItemPrice::getExtensionName());

                $child->addExtension(
                    CrehlerLineItemPrice::getExtensionName(),
                    new CrehlerLineItemPrice(
                        $currentCrehlerChildPrice->getQuantity() === 1 ? $currentCrehlerChildPrice->getUnitPrice() + round($pennyCorrection, 2) : $currentCrehlerChildPrice->getUnitPrice(),
                        $currentCrehlerChildPrice->getTotalPrice() + round($pennyCorrection, 2),
                        $currentCrehlerChildPrice->getCalculatedTaxes(),
                        $currentCrehlerChildPrice->getTaxRules(),
                        $currentCrehlerChildPrice->getQuantity(),
                        null,
                        null,
                        null
                    )
                );
            }
        }

        return $cart;
    }

    private function recalculateCrehlerPrice(CrehlerLineItemPrice $crehlerLineItemPrice, int $quantity, float $discount): CrehlerLineItemPrice
    {
        $itemUnitPrice = $this->amountFormat->floatToInt($crehlerLineItemPrice->getUnitPrice());
        $itemTotalPrice = $this->amountFormat->floatToInt($crehlerLineItemPrice->getTotalPrice());
        $discount = $this->amountFormat->floatToInt($discount);
        $unitDiscount = (int) round($discount / $quantity);
        $itemUnitPrice = $itemUnitPrice - $unitDiscount;
        $itemTotalPrice = $itemTotalPrice - $discount;
        $newTaxCollection = new CalculatedTaxCollection();

        foreach ($crehlerLineItemPrice->getCalculatedTaxes() as $calculatedTax) {
            $taxRate = $calculatedTax->getTaxRate();
            $vatDivisor = 1 + ($taxRate / 100);
            $vatAmount = (int) round($itemTotalPrice - ($itemTotalPrice / (1 + ($calculatedTax->getTaxRate() / 100))));
            $newTaxCollection->add(new CalculatedTax($this->amountFormat->intToFloat($vatAmount), $calculatedTax->getTaxRate(), $this->amountFormat->intToFloat($itemTotalPrice)));
        }

        return new CrehlerLineItemPrice(
            $this->amountFormat->intToFloat($itemUnitPrice),
            $this->amountFormat->intToFloat($itemTotalPrice),
            $newTaxCollection,
            $crehlerLineItemPrice->getTaxRules(),
            $crehlerLineItemPrice->getQuantity(),
            null,
            null,
            null
        );
    }

    private function createCrehlerPriceFormCalculatedPrice(?CalculatedPrice $price): ?CrehlerLineItemPrice
    {
        if ($price === null) {
            return null;
        }

        return CrehlerLineItemPrice::createFrom($price);
    }
}
