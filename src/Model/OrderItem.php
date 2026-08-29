<?php

declare(strict_types=1);

namespace CommerceML2\Model;

/** Позиция заказа (orders.xml: Заказ/Товары/Товар). */
final class OrderItem
{
    public function __construct(
        public readonly string $productId,
        public readonly string $name,
        public readonly float $quantity,
        public readonly float $price,
        public readonly ?string $unit = null,
    ) {
    }
}
