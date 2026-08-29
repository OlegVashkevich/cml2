<?php

declare(strict_types=1);

namespace CommerceML2\Parser;

use CommerceML2\Exception\ExchangeException;
use CommerceML2\Model\Order;
use CommerceML2\Model\OrderItem;

/**
 * Разбирает orders.xml, присланный 1С (обычно — обновлённые статусы ранее выгруженных заказов,
 * иногда — заказы, созданные в самой 1С).
 */
final class OrdersParser
{
    /** @param callable(Order):void $onOrder */
    public function parse(string $xmlContent, callable $onOrder): void
    {
        $reader = new \XMLReader();

        if (!$reader->XML($xmlContent, null, LIBXML_NOWARNING | LIBXML_NOERROR)) {
            throw new ExchangeException('Cannot parse orders.xml: invalid XML');
        }

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'Документ') {
                continue;
            }

            $node = $reader->expand();
            $dom = new \DOMDocument();
            $imported = $dom->importNode($node, true);
            $dom->appendChild($imported);
            $simple = simplexml_import_dom($imported);

            if ($simple === false) {
                continue;
            }

            $onOrder($this->parseOrder($simple));
        }

        $reader->close();
    }

    private function parseOrder(\SimpleXMLElement $node): Order
    {
        $id = (string) $node->Ид;
        $dateRaw = (string) $node->Дата;
        $timeRaw = (string) ($node->Время ?? '00:00:00');

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateRaw . ' ' . $timeRaw)
            ?: new \DateTimeImmutable();

        $status = null;
        if (isset($node->ХодСтатуса) || isset($node->Статус)) {
            $status = (string) ($node->Статус ?? $node->ХодСтатуса);
        }

        $currency = isset($node->Валюта) ? (string) $node->Валюта : 'RUB';
        $total = isset($node->Сумма) ? (float) $node->Сумма : 0.0;

        $items = [];
        if (isset($node->Товары->Товар)) {
            foreach ($node->Товары->Товар as $item) {
                $items[] = new OrderItem(
                    productId: (string) $item->Ид,
                    name: (string) $item->Наименование,
                    quantity: (float) $item->Количество,
                    price: (float) $item->ЦенаЗаЕдиницу,
                    unit: isset($item->Единица) ? (string) $item->Единица : null,
                );
            }
        }

        $customerName = null;
        $customerPhone = null;
        $customerEmail = null;

        if (isset($node->Контрагенты->Контрагент)) {
            $contragent = $node->Контрагенты->Контрагент;
            $customerName = isset($contragent->Наименование) ? (string) $contragent->Наименование : null;

            if (isset($contragent->Контакты->Контакт)) {
                foreach ($contragent->Контакты->Контакт as $contact) {
                    $type = (string) ($contact->Тип ?? '');
                    $value = (string) ($contact->Значение ?? '');
                    if (stripos($type, 'телефон') !== false) {
                        $customerPhone = $value;
                    } elseif (stripos($type, 'mail') !== false) {
                        $customerEmail = $value;
                    }
                }
            }
        }

        return new Order(
            id: $id,
            date: $date,
            currency: $currency,
            total: $total,
            items: $items,
            status: $status,
            customerName: $customerName,
            customerPhone: $customerPhone,
            customerEmail: $customerEmail,
            comment: isset($node->Комментарий) ? (string) $node->Комментарий : null,
        );
    }
}
