# commerceml2

Универсальный PHP-пакет для обмена данными по протоколу **CommerceML 2**
(стандартный обмен "1С — сайт": каталог `import.xml`/`offers.xml`, заказы `orders.xml`).

Пакет реализует именно **протокол** (HTTP-хендшейк `checkauth → init → file → import → query → success`
и парсинг/генерацию XML), а хранение данных и бизнес-логику вы подключаете сами через интерфейсы —
пакет не зависит от конкретной CMS/ORM/БД.

## Состав

- `ExchangeHandler` — диспетчер HTTP-запросов от 1С, реализует протокол.
- `Auth\AuthenticatorInterface` (+ готовая `BasicAuthSessionAuthenticator`) — аутентификация.
- `Storage\FileStorageInterface` (+ готовая `LocalFileStorage`) — хранение загруженных файлов.
- `Parser\CatalogParser` — потоковый разбор `import.xml` (категории + товары).
- `Parser\OffersParser` — потоковый разбор `offers.xml` (цены/остатки).
- `Parser\OrdersParser` — разбор `orders.xml`, присланного 1С (статусы заказов).
- `Xml\OrdersXmlWriter` — генерация `orders.xml` для отдачи 1С (заказы сайта).
- `Contract\CatalogImportHandlerInterface`, `Contract\OrderExchangeHandlerInterface` —
  интерфейсы, которые реализуете вы под свою БД.

## Быстрый старт

```bash
composer install
```

1. Скопируйте `examples/exchange.php`, `CatalogImporterExample.php`, `OrderExchangeExample.php`
   в свой проект и реализуйте методы `save*`/`fetchPendingOrders` под вашу БД.
2. Разместите `exchange.php` по адресу, который укажете в 1С в настройках выгрузки на сайт
   (обычно раздел "Обмен данными с сайтом" / CommerceML).
3. В 1С в настройках обмена укажите:
   - адрес: `https://ваш-сайт/.../exchange.php`
   - логин/пароль — те же, что в `BasicAuthSessionAuthenticator`.
4. Убедитесь, что веб-сервер прокидывает `PHP_AUTH_USER`/`PHP_AUTH_PW` в PHP
   (при использовании HTTP Basic Auth через сам PHP; при Basic Auth на уровне Apache/Nginx
   с проксированием на PHP это тоже нужно явно включить, см. `SetEnvIf`/`fastcgi_param`).

## Как это работает (протокол)

1С обращается по HTTP с параметрами `?type=catalog|sale&mode=checkauth|init|file|import|query|success`:

- **checkauth** — проверка логина/пароля, в ответ отдаётся имя и значение сессии.
- **init** — сообщаем 1С лимит размера файла и поддержку zip.
- **file** — 1С заливает файлы (`import.xml`, `offers.xml`, картинки) по одному за запрос,
  тело запроса — сырое содержимое файла, имя — в `?filename=`.
- **import** — просим применить ранее залитый файл: пакет вызывает
  `CatalogImportHandlerInterface::import()` (для `type=catalog`) или
  `OrderExchangeHandlerInterface::importIncoming()` (для `type=sale`).
  Оба метода возвращают `ImportProgress`: `ImportProgress::done()` → ответ `success`;
  `ImportProgress::inProgress()` → ответ `progress`, и 1С сама повторит тот же запрос
  `mode=import&filename=...` позже. Это штатный механизм протокола для больших файлов —
  см. раздел "Большие каталоги" ниже и `Parser\CatalogParser` (параметры
  `skipProducts`/`shouldPause`).
- **query** (только `type=sale`) — 1С запрашивает новые заказы сайта,
  пакет вызывает `OrderExchangeHandlerInterface::generateOutgoingOrdersXml()`.
- **success** — 1С подтвердила приём, вызывается `confirmDelivered()`.

## Большие каталоги (десятки/сотни тысяч товаров)

1С обычно сама режет крупные `import.xml`/`offers.xml` на несколько файлов
(`import0_1.xml`, `import1_1.xml`, ...), так что проблема "весь каталог одним файлом"
на практике редкая. Но даже один файл-кусок может обрабатываться дольше, чем позволяет
HTTP-таймаут. Для этого в протоколе предусмотрен ответ `progress` вместо `success` на
`mode=import` — 1С в этом случае сама повторит тот же запрос позже, пока не получит `success`.

Пакет поддерживает это "из коробки":

1. `CatalogImportHandlerInterface::import()` возвращает `ImportProgress`.
2. `CatalogParser::parse()` принимает `skipProducts` (сколько товаров уже обработано
   в прошлые заходы — они будут быстро пропущены без разбора полей) и `shouldPause`
   (колбэк, например по таймеру, после которого разбор прерывается).
3. Между вызовами нужно хранить курсор (сколько товаров обработано) — например,
   в отдельной таблице `import_progress(filename, cursor)`. Рабочий пример — в
   `examples/CatalogImporterExample.php`.

Прежде чем городить очереди/воркеры под это — почти всегда достаточно батчевого upsert'а
в БД (пачками по 500-1000 строк) и вот этого `progress`-механизма. Очередь имеет смысл
доставать, только если обработка каждого товара сама по себе дорогая (внешние API,
генерация изображений) — тогда выигрыш даёт параллелизм, а не просто "успеть в таймаут".

## Важные ограничения / TODO при доработке под прод

- `CatalogParser` разбирает базовую структуру `import.xml` (Ид, Наименование, Артикул,
  Описание, Группы, ЗначенияСвойств, Картинка). Полная схема CommerceML 2 включает также
  отдельный справочник `Свойства` с перечислениями (`ВариантыЗначений`) в начале
  `Классификатор` — если вам нужны имена свойств/enum-значения, а не только их Ид,
  расширьте парсер разбором этого раздела (структура в файле похожая, для краткости
  в базовой версии не включена).
- `BasicAuthSessionAuthenticator` — самый простой вариант аутентификации. Если ваш веб-сервер
  не прокидывает Basic Auth в PHP, либо 1С настроена на обмен без Basic Auth,
  реализуйте свой `AuthenticatorInterface` (например, на базе своих API-токенов).
- `LocalFileStorage` пишет файлы на диск как есть; при необходимости добавьте свою
  реализацию `FileStorageInterface` (например, с проверкой MIME для картинок).
- Большие каталоги: 1С может слать `import.xml`/`offers.xml` частями с одинаковым `filename`
  и повторными `mode=file` запросами — `LocalFileStorage::put()` в этой версии перезаписывает
  файл целиком при каждом вызове; для докачки по частям добавьте дозапись (`fopen('ab')`)
  по смещению, если увидите в логах, что 1С шлёт файл несколькими кусками.
- Кодировка: `import.xml`/`offers.xml` от 1С часто в `windows-1251`. `XMLReader`/`SimpleXML`
  сами сконвертируют строки в UTF-8 при чтении атрибута `encoding` в прологе XML — но
  проверьте это на реальной выгрузке вашей конфигурации 1С.

## Лицензия

MIT — используйте и дорабатывайте под свои нужды.
