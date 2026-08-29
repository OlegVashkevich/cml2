<?php

declare(strict_types=1);

namespace CommerceML2;

use CommerceML2\Auth\AuthenticatorInterface;
use CommerceML2\Contract\CatalogImportHandlerInterface;
use CommerceML2\Contract\OrderExchangeHandlerInterface;
use CommerceML2\Exception\AuthException;
use CommerceML2\Exception\ExchangeException;
use CommerceML2\Storage\FileStorageInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Реализует серверную часть протокола CommerceML 2 ("Обмен данными с сайтом" со стороны 1С).
 *
 * 1С обращается к вашему PHP-скрипту (обычно /bitrix/admin/1c_exchange.php или
 * произвольный exchange.php) с GET/POST-параметрами:
 *   type = catalog | sale
 *   mode = checkauth | init | file | import | query | success
 *
 * Класс сам ничего не знает про конкретную CMS/БД — вся бизнес-логика
 * (сохранение файлов, импорт товаров, работа с заказами) вынесена в интерфейсы:
 *   FileStorageInterface, CatalogImportHandlerInterface, OrderExchangeHandlerInterface.
 */
final class ExchangeHandler
{
    /** Лимит размера одного файла, который 1С будет заливать за один POST-запрос (байт). */
    private int $fileLimitBytes;

    public function __construct(
        private readonly AuthenticatorInterface $auth,
        private readonly FileStorageInterface $storage,
        private readonly CatalogImportHandlerInterface $catalogHandler,
        private readonly OrderExchangeHandlerInterface $orderHandler,
        private readonly LoggerInterface $logger = new NullLogger(),
        int $fileLimitBytes = 100 * 1024 * 1024, // 100 МБ по умолчанию
    ) {
        $this->fileLimitBytes = $fileLimitBytes;
    }

    /**
     * Точка входа. Вызывается из вашего index.php / exchange.php.
     *
     * @param array<string,string> $query   $_GET
     * @param string               $rawBody Тело запроса (например file_get_contents('php://input'))
     */
    public function handle(array $query, string $rawBody): Response
    {
        $type = $query['type'] ?? '';
        $mode = $query['mode'] ?? '';

        if (!in_array($type, ['catalog', 'sale'], true)) {
            return Response::plain('failure Unknown type: ' . $type, 400);
        }

        try {
            // checkauth — единственный режим, для которого сессия ещё не обязана существовать
            if ($mode === 'checkauth') {
                return $this->handleCheckAuth();
            }

            // Все остальные режимы требуют уже установленной (после checkauth) авторизации
            $this->auth->verify();

            return match ($mode) {
                'init'    => $this->handleInit($type),
                'file'    => $this->handleFile($type, $query, $rawBody),
                'import'  => $this->handleImport($type, $query),
                'query'   => $this->handleQuery($type),
                'success' => $this->handleSuccess($type, $query),
                default   => Response::plain('failure Unknown mode: ' . $mode, 400),
            };
        } catch (AuthException $e) {
            $this->logger->warning('CommerceML2 auth failed: ' . $e->getMessage());
            return Response::plain('failure ' . $e->getMessage(), 401);
        } catch (ExchangeException $e) {
            $this->logger->error('CommerceML2 exchange error: ' . $e->getMessage());
            return Response::plain('failure ' . $e->getMessage(), 500);
        } catch (\Throwable $e) {
            $this->logger->error('CommerceML2 unexpected error: ' . $e->getMessage(), ['exception' => $e]);
            return Response::plain('failure Internal error', 500);
        }
    }

    private function handleCheckAuth(): Response
    {
        // AuthenticatorInterface сам решает: HTTP Basic Auth, отдельная сессия и т.п.
        $session = $this->auth->authenticate();

        if ($session === null) {
            return Response::plain('failure', 401);
        }

        // Формат ответа 1С ожидает строго так (3 строки):
        // success
        // <имя cookie/переменной сессии>
        // <значение>
        $body = "success\n{$session->name}\n{$session->value}";

        $response = Response::plain($body, 200);

        if ($session->cookie) {
            $response->withCookie($session->name, $session->value);
        }

        return $response;
    }

    private function handleInit(string $type): Response
    {
        // zip=no означает, что мы не поддерживаем прием зазипованных файлов (можно включить,
        // если ваш сервер умеет распаковывать на лету — тут для простоты выключено).
        $body = "zip=no\nfile_limit=" . $this->fileLimitBytes;

        $this->logger->info("CommerceML2 init: type={$type}");

        return Response::plain($body);
    }

    private function handleFile(string $type, array $query, string $rawBody): Response
    {
        $filename = $query['filename'] ?? '';

        if ($filename === '') {
            throw new ExchangeException('Missing filename');
        }

        // Защита от path traversal — 1С присылает относительный путь вида
        // "import.xml" или "import_files/catalog/goods/12345.jpg"
        $safePath = $this->sanitizeRelativePath($filename);

        $this->storage->put($type, $safePath, $rawBody);

        $this->logger->info("CommerceML2 file saved: {$safePath} (" . strlen($rawBody) . ' bytes)');

        return Response::plain('success');
    }

    private function handleImport(string $type, array $query): Response
    {
        $filename = $query['filename'] ?? '';
        $safePath = $this->sanitizeRelativePath($filename);

        $content = $this->storage->get($type, $safePath);

        if ($content === null) {
            throw new ExchangeException("File not found for import: {$safePath}");
        }

        if ($type === 'catalog') {
            // import.xml — классификатор + товары; offers.xml — торговые предложения (цены/остатки)
            $progress = $this->catalogHandler->import($safePath, $content);
        } else {
            // type === 'sale': сюда прилетает orders.xml от 1С со статусами существующих заказов
            $progress = $this->orderHandler->importIncoming($content);
        }

        if (!$progress->done) {
            // 1С прочитает "progress" и повторит тот же запрос mode=import&filename=...
            // через паузу (по умолчанию несколько секунд) — так и должно быть для больших файлов.
            $this->logger->info("CommerceML2 import in progress: {$safePath}" . ($progress->note !== null ? " ({$progress->note})" : ''));

            $body = 'progress';
            if ($progress->note !== null) {
                $body .= "\n" . $progress->note;
            }

            return Response::plain($body);
        }

        $this->logger->info("CommerceML2 import done: {$safePath}");

        return Response::plain('success');
    }

    private function handleQuery(string $type): Response
    {
        if ($type !== 'sale') {
            return Response::plain('failure', 400);
        }

        // Отдаём 1С XML с новыми/изменёнными заказами сайта
        $xml = $this->orderHandler->generateOutgoingOrdersXml();

        return Response::xml($xml);
    }

    private function handleSuccess(string $type, array $query): Response
    {
        if ($type === 'sale') {
            // 1С подтвердила получение orders.xml — можно пометить заказы как отправленные
            $this->orderHandler->confirmDelivered();
        }

        return Response::plain('success');
    }

    private function sanitizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new ExchangeException('Invalid path (path traversal detected)');
            }
            $parts[] = $segment;
        }

        if ($parts === []) {
            throw new ExchangeException('Empty filename');
        }

        return implode('/', $parts);
    }
}
