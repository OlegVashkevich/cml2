<?php

declare(strict_types=1);

namespace CommerceML2\Storage;

use CommerceML2\Exception\ExchangeException;

final class LocalFileStorage implements FileStorageInterface
{
    public function __construct(
        private readonly string $baseDir,
    ) {
    }

    public function put(string $type, string $relativePath, string $content): void
    {
        $fullPath = $this->resolve($type, $relativePath);
        $dir = dirname($fullPath);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new ExchangeException("Cannot create directory: {$dir}");
        }

        // 1С может присылать файл несколькими POST-запросами частями (докачка) —
        // но чаще всего по сумме это один PUT/POST на файл, поэтому просто перезаписываем.
        if (file_put_contents($fullPath, $content) === false) {
            throw new ExchangeException("Cannot write file: {$fullPath}");
        }
    }

    public function get(string $type, string $relativePath): ?string
    {
        $fullPath = $this->resolve($type, $relativePath);

        if (!is_file($fullPath)) {
            return null;
        }

        $content = file_get_contents($fullPath);

        return $content === false ? null : $content;
    }

    private function resolve(string $type, string $relativePath): string
    {
        return rtrim($this->baseDir, '/') . '/' . $type . '/' . $relativePath;
    }
}
