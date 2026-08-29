<?php

declare(strict_types=1);

namespace CommerceML2\Model;

/** Товар из import.xml (Классификатор + Каталог/Товары/Товар). */
final class Product
{
    /**
     * @param string[]        $categoryIds Ссылки на Category::$id
     * @param PropertyValue[] $properties
     * @param string[]        $images      относительные пути к картинкам (уже сохранённым через FileStorage)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $article = null,
        public readonly ?string $description = null,
        public readonly array $categoryIds = [],
        public readonly array $properties = [],
        public readonly array $images = [],
        public readonly ?string $baseUnit = null,
    ) {
    }
}
