<?php

declare(strict_types=1);

namespace CommerceML2\Contract;

/** Реализуется на стороне сайта/CMS для обмена заказами (type=sale). */
interface OrderExchangeHandlerInterface
{
    /** mode=query: сформировать orders.xml с новыми/изменёнными заказами сайта для 1С. */
    public function generateOutgoingOrdersXml(): string;

    /** mode=success: 1С подтвердила получение orders.xml — можно пометить заказы как выгруженные. */
    public function confirmDelivered(): void;

    /**
     * mode=import (type=sale): 1С прислала orders.xml обратно — обычно это статусы заказов.
     * orders.xml обычно на порядки меньше import.xml, так что почти всегда можно
     * сразу вернуть ImportProgress::done() — но интерфейс симметричен с каталогом
     * на случай, если у вас тоже тысячи заказов за раз.
     */
    public function importIncoming(string $xmlContent): ImportProgress;
}
