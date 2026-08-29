<?php

declare(strict_types=1);

namespace CommerceML2\Model;

/** Торговое предложение из offers.xml (ПакетПредложений/Предложения/Предложение). */
final class Offer
{
    /** @param array<int, array{value: float, currency: string, type: string}> $prices */
    public function __construct(
        public readonly string $id,
        /** Ссылка на Product::$id (Ид предложения без суффикса характеристики, либо совпадает с Ид товара) */
        public readonly string $productId,
        public readonly array $prices = [],
        public readonly ?float $quantity = null,
        public readonly ?string $unit = null,
    ) {
    }
}
