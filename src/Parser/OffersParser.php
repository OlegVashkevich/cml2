<?php

declare(strict_types=1);

namespace CommerceML2\Parser;

use CommerceML2\Exception\ExchangeException;
use CommerceML2\Model\Offer;
use DOMDocument;
use SimpleXMLElement;
use XMLReader;

/**
 * Разбирает offers.xml (КоммерческаяИнформация/ПакетПредложений/Предложения/Предложение).
 * Потоковый разбор через XMLReader — годится для больших выгрузок остатков/цен.
 */
final class OffersParser
{
    /** @param callable(Offer):void $onOffer */
    public function parse(string $xmlContent, callable $onOffer): void
    {
        $reader = new XMLReader();

        if (!$reader->XML($xmlContent, null, LIBXML_NOWARNING | LIBXML_NOERROR)) {
            throw new ExchangeException('Cannot parse offers.xml: invalid XML');
        }

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'Предложение') {
                continue;
            }

            $node = $this->expand($reader);
            $onOffer($this->parseOffer($node));
        }

        $reader->close();
    }

    private function expand(XMLReader $reader): SimpleXMLElement
    {
        $node = $reader->expand();
        $dom = new DOMDocument();
        $imported = $dom->importNode($node, true);
        $dom->appendChild($imported);

        $simple = simplexml_import_dom($imported);
        if ($simple === false) {
            throw new ExchangeException('Cannot expand XML node: Предложение');
        }

        return $simple;
    }

    private function parseOffer(SimpleXMLElement $node): Offer
    {
        $id = (string) $node->Ид;

        // Ид предложения обычно вида "<ИдТовара>" или "<ИдТовара>#<ИдХарактеристики>"
        $productId = str_contains($id, '#') ? explode('#', $id, 2)[0] : $id;

        $prices = [];
        if (isset($node->Цены->Цена)) {
            foreach ($node->Цены->Цена as $price) {
                $prices[] = [
                    'value' => (float) $price->ЦенаЗаЕдиницу,
                    'currency' => (string) $price->Валюта,
                    'type' => (string) $price->ИдТипаЦены,
                ];
            }
        }

        $quantity = isset($node->Количество) ? (float) $node->Количество : null;
        $unit = isset($node->Единица) ? (string) $node->Единица : null;

        return new Offer(
            id: $id,
            productId: $productId,
            prices: $prices,
            quantity: $quantity,
            unit: $unit,
        );
    }
}
