<?php

declare(strict_types=1);

use CommerceML2\Contract\CatalogImportHandlerInterface;
use CommerceML2\Contract\ImportProgress;
use CommerceML2\Model\Category;
use CommerceML2\Model\Offer;
use CommerceML2\Model\Product;
use CommerceML2\Parser\CatalogParser;
use CommerceML2\Parser\OffersParser;

/**
 * Пример: сохраняем товары/категории/цены в свою БД, с поддержкой чанкованного
 * (возобновляемого) импорта — актуально, когда 1С прислала import.xml/offers.xml
 * на десятки-сотни тысяч позиций и обработать всё за один HTTP-запрос нереально.
 *
 * Идея: обрабатываем товары порциями по TIME_BUDGET_SECONDS секунд, между вызовами
 * храним "сколько товаров уже обработано" (курсор) — например, в отдельной таблице
 * import_progress(filename VARCHAR, cursor INT), ключ — filename.
 * 1С сама повторит mode=import с тем же filename, пока не получит "success".
 */
final class CatalogImporterExample implements CatalogImportHandlerInterface
{
    /** Сколько секунд обрабатываем за один HTTP-запрос, прежде чем ответить "progress". */
    private const TIME_BUDGET_SECONDS = 15;

    public function import(string $relativePath, string $xmlContent): ImportProgress
    {
        $basename = basename($relativePath);

        if ($basename === 'import.xml') {
            return $this->importClassifierAndProducts($relativePath, $xmlContent);
        }

        if ($basename === 'offers.xml') {
            return $this->importOffers($xmlContent);
        }

        // Картинки (import_files/...) сюда не попадают — они просто сохраняются
        // в LocalFileStorage на mode=file, обращайтесь к ним по путям из Product::$images.
        return ImportProgress::done();
    }

    private function importClassifierAndProducts(string $relativePath, string $xml): ImportProgress
    {
        $alreadyProcessed = $this->loadCursor($relativePath);
        $deadline = microtime(true) + self::TIME_BUDGET_SECONDS;

        $parser = new CatalogParser();

        $result = $parser->parse(
            $xml,
            onCategory: function (Category $category): void {
                $this->saveCategory($category);
            },
            onProduct: function (Product $product): void {
                $this->saveProduct($product);
            },
            skipProducts: $alreadyProcessed,
            shouldPause: static fn (): bool => microtime(true) >= $deadline,
        );

        if ($result->done) {
            $this->clearCursor($relativePath);
            return ImportProgress::done();
        }

        $newCursor = $alreadyProcessed + $result->processedThisRun;
        $this->saveCursor($relativePath, $newCursor);

        return ImportProgress::inProgress("Обработано товаров: {$newCursor}");
    }

    private function importOffers(string $xml): ImportProgress
    {
        // offers.xml обычно значительно легче import.xml (цена/остаток — пара полей на
        // товар, без описаний/картинок), поэтому в большинстве случаев чанкование не
        // требуется — но при желании применяется тот же приём: добавьте в OffersParser
        // аналогичные skipOffers/shouldPause параметры по образцу CatalogParser выше.
        $parser = new OffersParser();

        $parser->parse(
            $xml,
            onOffer: function (Offer $offer): void {
                $this->saveOffer($offer);
            },
        );

        return ImportProgress::done();
    }

    private function saveCategory(Category $category): void
    {
        // TODO: INSERT ... ON DUPLICATE KEY UPDATE в вашу таблицу категорий (идемпотентно —
        // категории обрабатываются заново при каждом возобновлении разбора).
        // $this->db->upsertCategory($category->id, $category->name, $category->parentId);
    }

    private function saveProduct(Product $product): void
    {
        // TODO: upsert товара + его связей с категориями/свойствами/картинками.
        // Тоже должен быть идемпотентным — на случай ретрая HTTP-запроса от 1С.
        // $this->db->upsertProduct(...);
    }

    private function saveOffer(Offer $offer): void
    {
        // TODO: обновить цену/остаток товара по $offer->productId
        // $price = $offer->prices[0]['value'] ?? null;
        // $this->db->updatePriceAndStock($offer->productId, $price, $offer->quantity);
    }

    private function loadCursor(string $relativePath): int
    {
        // TODO: SELECT cursor FROM import_progress WHERE filename = $relativePath
        return 0;
    }

    private function saveCursor(string $relativePath, int $count): void
    {
        // TODO: INSERT ... ON DUPLICATE KEY UPDATE cursor = $count
    }

    private function clearCursor(string $relativePath): void
    {
        // TODO: DELETE FROM import_progress WHERE filename = $relativePath
    }
}
