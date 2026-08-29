<?php

declare(strict_types=1);

namespace CommerceML2\Parser;

use CommerceML2\Exception\ExchangeException;
use CommerceML2\Model\Category;
use CommerceML2\Model\Product;
use CommerceML2\Model\PropertyValue;

/**
 * Разбирает import.xml (КоммерческаяИнформация/Классификатор + .../Каталог).
 * Работает потоково через XMLReader — подходит для больших файлов (тысячи товаров),
 * не загружает весь XML в память как DOM.
 *
 * Поддерживает возобновляемый (chunked) разбор для больших каталогов: если товаров
 * слишком много, чтобы обработать их за один HTTP-запрос от 1С без таймаута,
 * передайте $shouldPause (например, "прошло больше 15 секунд") — parse() остановится
 * и вернёт CatalogParseResult::$done = false. При следующем вызове передайте
 * $skipProducts = <сколько уже обработано раньше> — уже пройденные товары будут
 * быстро пропущены (без вызова onProduct), и разбор продолжится с этого места.
 * См. ImportProgress и пример в examples/CatalogImporterExample.php.
 *
 * Использование:
 *   $result = $parser->parse($xmlContent,
 *       onCategory: fn(Category $c) => ...,
 *       onProduct:  fn(Product $p) => ...,
 *       skipProducts: 5000,
 *       shouldPause: fn() => microtime(true) >= $deadline,
 *   );
 */
final class CatalogParser
{
    /**
     * @param callable(Category):void $onCategory
     * @param callable(Product):void  $onProduct
     * @param int                     $skipProducts сколько первых Товар-узлов пропустить без обработки
     *                                              (продолжение прерванного ранее разбора)
     * @param callable(): bool|null   $shouldPause  вызывается после каждого обработанного товара;
     *                                              если вернёт true — разбор останавливается досрочно
     */
    public function parse(
        string $xmlContent,
        callable $onCategory,
        callable $onProduct,
        int $skipProducts = 0,
        ?callable $shouldPause = null,
    ): CatalogParseResult {
        $reader = new \XMLReader();

        if (!$reader->XML($xmlContent, null, LIBXML_NOWARNING | LIBXML_NOERROR)) {
            throw new ExchangeException('Cannot parse import.xml: invalid XML');
        }

        $seenProducts = 0;
        $processedThisRun = 0;
        $done = true;

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }

            if ($reader->name === 'Группа') {
                // Категорий обычно немного (десятки-сотни), поэтому пропуск/пауза для них
                // не реализуем — они всегда обрабатываются целиком на каждом проходе.
                // saveCategory() на стороне CMS должен быть идемпотентным upsert'ом,
                // так что повторная обработка при возобновлении разбора не страшна.
                $node = $this->expand($reader);
                $this->walkCategories($node, null, $onCategory);
                continue;
            }

            if ($reader->name === 'Товар') {
                $seenProducts++;

                if ($seenProducts <= $skipProducts) {
                    // Уже обработан в прошлый вызов parse() — пропускаем без expand()/парсинга полей,
                    // это заметно дешевле, чем собирать Product и вызывать onProduct.
                    continue;
                }

                $node = $this->expand($reader);
                $onProduct($this->parseProduct($node));
                $processedThisRun++;

                if ($shouldPause !== null && $shouldPause()) {
                    $done = false;
                    break;
                }
            }
        }

        $reader->close();

        return new CatalogParseResult($done, $seenProducts, $processedThisRun);
    }

    private function expand(\XMLReader $reader): \SimpleXMLElement
    {
        $node = $reader->expand();
        $dom = new \DOMDocument();
        $imported = $dom->importNode($node, true);
        $dom->appendChild($imported);

        $simple = simplexml_import_dom($imported);
        if ($simple === false) {
            throw new ExchangeException('Cannot expand XML node: ' . $reader->name);
        }

        return $simple;
    }

    /**
     * Группы в CommerceML вложенные (Группа может содержать дочерние Группа рекурсивно).
     *
     * @param callable(Category):void $onCategory
     */
    private function walkCategories(\SimpleXMLElement $group, ?string $parentId, callable $onCategory): void
    {
        $id = (string) $group->Ид;
        $name = (string) $group->Наименование;

        $onCategory(new Category($id, $name, $parentId));

        if (isset($group->Группы->Группа)) {
            foreach ($group->Группы->Группа as $child) {
                $this->walkCategories($child, $id, $onCategory);
            }
        }
    }

    private function parseProduct(\SimpleXMLElement $node): Product
    {
        $id = (string) $node->Ид;
        $name = (string) $node->Наименование;
        $article = isset($node->Артикул) ? (string) $node->Артикул : null;
        $description = isset($node->Описание) ? (string) $node->Описание : null;
        $baseUnit = isset($node->БазоваяЕдиница) ? (string) $node->БазоваяЕдиница : null;

        $categoryIds = [];
        if (isset($node->Группы->Ид)) {
            foreach ($node->Группы->Ид as $groupId) {
                $categoryIds[] = (string) $groupId;
            }
        }

        $images = [];
        if (isset($node->Картинка)) {
            foreach ($node->Картинка as $image) {
                $images[] = (string) $image;
            }
        }

        $properties = [];
        if (isset($node->ЗначенияСвойств->ЗначенияСвойства)) {
            foreach ($node->ЗначенияСвойств->ЗначенияСвойства as $prop) {
                $properties[] = new PropertyValue(
                    (string) $prop->Ид,
                    (string) $prop->Значение,
                );
            }
        }

        return new Product(
            id: $id,
            name: $name,
            article: $article,
            description: $description,
            categoryIds: $categoryIds,
            properties: $properties,
            images: $images,
            baseUnit: $baseUnit,
        );
    }
}
