<?php

declare(strict_types=1);

namespace CommerceML2\Model;

/** Группа/категория из классификатора (Классификатор/Группы). */
final class Category
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $parentId = null,
    ) {
    }
}
