<?php

declare(strict_types=1);

namespace CommerceML2\Model;

/** Значение свойства/характеристики товара (ХарактеристикиТовара / ЗначенияСвойства). */
final class PropertyValue
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
    ) {
    }
}
