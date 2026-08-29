<?php

declare(strict_types=1);

namespace CommerceML2\Xml;

use CommerceML2\Model\Order;

/**
 * Формирует orders.xml (КоммерческаяИнформация/Документ...) для ответа на mode=query,
 * чтобы 1С забрала новые заказы сайта.
 */
final class OrdersXmlWriter
{
    /** @param Order[] $orders */
    public function write(array $orders, ?\DateTimeInterface $date = null): string
    {
        $date ??= new \DateTimeImmutable();

        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->startDocument('1.0', 'UTF-8');

        $writer->startElement('КоммерческаяИнформация');
        $writer->writeAttribute('ВерсияСхемы', '2.10');
        $writer->writeAttribute('ДатаФормирования', $date->format('Y-m-d\TH:i:s'));

        foreach ($orders as $order) {
            $this->writeOrder($writer, $order);
        }

        $writer->endElement(); // КоммерческаяИнформация
        $writer->endDocument();

        return $writer->outputMemory();
    }

    private function writeOrder(\XMLWriter $writer, Order $order): void
    {
        $writer->startElement('Документ');
        $writer->writeElement('Ид', $order->id);
        $writer->writeElement('Номер', $order->id);
        $writer->writeElement('Дата', $order->date->format('Y-m-d'));
        $writer->writeElement('Время', $order->date->format('H:i:s'));
        $writer->writeElement('ХозОперация', 'Заказ товара');
        $writer->writeElement('Роль', 'Продавец');
        $writer->writeElement('Валюта', $order->currency);
        $writer->writeElement('Курс', '1');
        $writer->writeElement('Сумма', (string) $order->total);

        if ($order->status !== null) {
            $writer->writeElement('Статус', $order->status);
        }
        if ($order->comment !== null) {
            $writer->writeElement('Комментарий', $order->comment);
        }

        if ($order->customerName || $order->customerPhone || $order->customerEmail) {
            $writer->startElement('Контрагенты');
            $writer->startElement('Контрагент');
            $writer->writeElement('Ид', $order->id . '-customer');
            $writer->writeElement('Наименование', $order->customerName ?? '');
            $writer->writeElement('Роль', 'Покупатель');

            $writer->startElement('Контакты');
            if ($order->customerPhone !== null) {
                $writer->startElement('Контакт');
                $writer->writeElement('Тип', 'Телефон');
                $writer->writeElement('Значение', $order->customerPhone);
                $writer->endElement();
            }
            if ($order->customerEmail !== null) {
                $writer->startElement('Контакт');
                $writer->writeElement('Тип', 'Почта');
                $writer->writeElement('Значение', $order->customerEmail);
                $writer->endElement();
            }
            $writer->endElement(); // Контакты

            $writer->endElement(); // Контрагент
            $writer->endElement(); // Контрагенты
        }

        $writer->startElement('Товары');
        foreach ($order->items as $item) {
            $writer->startElement('Товар');
            $writer->writeElement('Ид', $item->productId);
            $writer->writeElement('Наименование', $item->name);
            if ($item->unit !== null) {
                $writer->writeElement('БазоваяЕдиница', $item->unit);
            }
            $writer->writeElement('Количество', (string) $item->quantity);
            $writer->writeElement('ЦенаЗаЕдиницу', (string) $item->price);
            $writer->writeElement('Сумма', (string) ($item->price * $item->quantity));
            $writer->endElement(); // Товар
        }
        $writer->endElement(); // Товары

        $writer->endElement(); // Документ
    }
}
