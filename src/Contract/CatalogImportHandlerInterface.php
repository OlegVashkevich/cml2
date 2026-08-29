<?php

declare(strict_types=1);

namespace CommerceML2\Contract;

/**
 * Реализуется на стороне сайта/CMS. Вызывается при mode=import для type=catalog.
 * Пакет сам определяет тип файла (import.xml / offers.xml) по имени и вызывает
 * соответствующий парсер (см. Parser\CatalogParser, Parser\OffersParser),
 * а сюда просто передаёт готовый filename+content — вы можете распарсить
 * их сами через готовые парсеры пакета внутри своей реализации.
 */
interface CatalogImportHandlerInterface
{
    /**
     * @param string $relativePath например "import.xml" или "offers.xml"
     * @param string $xmlContent   сырой XML, как он был сохранён FileStorage
     */
    public function import(string $relativePath, string $xmlContent): void;
}
