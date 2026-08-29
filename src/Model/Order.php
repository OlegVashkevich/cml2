<?php

declare(strict_types=1);

namespace CommerceML2\Model;

/** Заказ (orders.xml: Документ). Используется и для исходящего, и для входящего направления. */
final class Order
{
    /** @param OrderItem[] $items */
    public function __construct(
        public readonly string $id,
        public readonly \DateTimeImmutable $date,
        public readonly string $currency,
        public readonly float $total,
        public readonly array $items,
        public readonly ?string $status = null,
        public readonly ?string $customerName = null,
        public readonly ?string $customerPhone = null,
        public readonly ?string $customerEmail = null,
        public readonly ?string $comment = null,
    ) {
    }
}
