<?php

declare(strict_types=1);

use CommerceML2\Contract\CatalogImportHandlerInterface;
use CommerceML2\Model\Category;
use CommerceML2\Model\Offer;
use CommerceML2\Model\Product;
use CommerceML2\Parser\CatalogParser;
use CommerceML2\Parser\OffersParser;

/**
 * Пример: сохраняем товары/категории/цены в свою БД.
 * Замените тела методов saveCategory/saveProduct/saveOffer на вызовы вашей ORM/репозитория.
 */
final class CatalogImporterExample implements CatalogImportHandlerInterface
{
    public function import(string $relativePath, string $xmlContent): void
    {
        $basename = basename($relativePath);

        if ($basename === 'import.xml') {
            $this->importClassifierAndProducts($xmlContent);
        } elseif ($basename === 'offers.xml') {
            $this->importOffers($xmlContent);
        }
        // Файлы картинок (import_files/...) сюда не попадают — они просто сохраняются
        // в LocalFileStorage на mode=file, и вы обращаетесь к ним по путям из Product::$images.
    }

    private function importClassifierAndProducts(string $xml): void
    {
        $parser = new CatalogParser();

        $parser->parse(
            $xml,
            onCategory: function (Category $category): void {
                $this->saveCategory($category);
            },
            onProduct: function (Product $product): void {
                $this->saveProduct($product);
            },
        );
    }

    private function importOffers(string $xml): void
    {
        $parser = new OffersParser();

        $parser->parse(
            $xml,
            onOffer: function (Offer $offer): void {
                $this->saveOffer($offer);
            },
        );
    }

    private function saveCategory(Category $category): void
    {
        // TODO: INSERT ... ON DUPLICATE KEY UPDATE в вашу таблицу категорий
        // $this->db->upsertCategory($category->id, $category->name, $category->parentId);
    }

    private function saveProduct(Product $product): void
    {
        // TODO: upsert товара + его связей с категориями/свойствами/картинками
        // $this->db->upsertProduct(...);
    }

    private function saveOffer(Offer $offer): void
    {
        // TODO: обновить цену/остаток товара по $offer->productId
        // $price = $offer->prices[0]['value'] ?? null;
        // $this->db->updatePriceAndStock($offer->productId, $price, $offer->quantity);
    }
}
