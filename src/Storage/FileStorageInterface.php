<?php

declare(strict_types=1);

namespace CommerceML2\Storage;

/**
 * Абстракция над хранением файлов, которые 1С заливает через mode=file
 * (import.xml, offers.xml, orders.xml, картинки товаров и т.п.).
 * $relativePath уже очищен от path traversal в ExchangeHandler.
 */
interface FileStorageInterface
{
    public function put(string $type, string $relativePath, string $content): void;

    public function get(string $type, string $relativePath): ?string;
}
