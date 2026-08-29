<?php

declare(strict_types=1);

use CommerceML2\Contract\ImportProgress;
use CommerceML2\Contract\OrderExchangeHandlerInterface;
use CommerceML2\Model\Order;
use CommerceML2\Parser\OrdersParser;
use CommerceML2\Xml\OrdersXmlWriter;

final class OrderExchangeExample implements OrderExchangeHandlerInterface
{
    public function generateOutgoingOrdersXml(): string
    {
        // TODO: выбрать из БД заказы, ещё не выгруженные в 1С (флаг exported_to_1c = 0)
        $orders = $this->fetchPendingOrders();

        return (new OrdersXmlWriter())->write($orders);
    }

    public function confirmDelivered(): void
    {
        // TODO: проставить флаг exported_to_1c = 1 тем заказам, что были в последнем query
        // (проще всего запомнить их id между вызовами query и success в сессии/кеше)
    }

    public function importIncoming(string $xmlContent): ImportProgress
    {
        // orders.xml от 1С обычно небольшой (статусы существующих заказов),
        // поэтому обрабатываем целиком за один вызов без чанкования.
        (new OrdersParser())->parse($xmlContent, function (Order $order): void {
            // TODO: обновить статус заказа в вашей БД по $order->id / $order->status
        });

        return ImportProgress::done();
    }

    /** @return Order[] */
    private function fetchPendingOrders(): array
    {
        // TODO: реальная выборка из БД
        return [];
    }
}
