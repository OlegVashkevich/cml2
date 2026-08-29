<?php

declare(strict_types=1);

namespace CommerceML2\Parser;

final class CatalogParseResult
{
    public function __construct(
        /** true — дошли до конца файла; false — остановились по shouldPause() */
        public readonly bool $done,
        /**
         * Сколько Товар-узлов встретилось за этот вызов parse() (включая пропущенные skipProducts).
         * Если $done === true — это и есть общее число товаров в файле.
         * Если $done === false — это позиция, на которой остановились (не итог по всему файлу).
         */
        public readonly int $totalSeenProducts,
        /** сколько товаров реально обработано (передано в onProduct) за этот вызов parse() */
        public readonly int $processedThisRun,
    ) {
    }
}
