<?php

declare(strict_types=1);

/**
 * Пример точки входа: разместите этот файл по адресу, который укажете
 * в настройках выгрузки 1С ("Настройки обмена с сайтом" -> адрес сайта),
 * например https://example.com/exchange1c/exchange.php
 *
 * В настройках 1С обязательно укажите тот же логин/пароль, что и здесь,
 * и включите HTTP Basic Auth (либо настройте её на уровне веб-сервера).
 */

require __DIR__ . '/../vendor/autoload.php';

use CommerceML2\Auth\BasicAuthSessionAuthenticator;
use CommerceML2\ExchangeHandler;
use CommerceML2\Storage\LocalFileStorage;

$auth = new BasicAuthSessionAuthenticator(
    login: getenv('CML_LOGIN') ?: 'exchange',
    password: getenv('CML_PASSWORD') ?: 'change-me',
);

$storage = new LocalFileStorage(__DIR__ . '/../var/exchange');

// Реализации ниже — под вашу CMS/БД, см. CatalogImporterExample.php и OrderExchangeExample.php
$catalogHandler = new CatalogImporterExample();
$orderHandler = new OrderExchangeExample();

require __DIR__ . '/CatalogImporterExample.php';
require __DIR__ . '/OrderExchangeExample.php';

$handler = new ExchangeHandler(
    auth: $auth,
    storage: $storage,
    catalogHandler: $catalogHandler,
    orderHandler: $orderHandler,
);

$response = $handler->handle(
    query: $_GET,
    rawBody: file_get_contents('php://input') ?: '',
);

$response->send();
