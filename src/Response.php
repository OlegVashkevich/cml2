<?php

declare(strict_types=1);

namespace CommerceML2;

final class Response
{
    /** @var array<int, array{name:string,value:string}> */
    private array $cookies = [];

    private function __construct(
        public readonly string $body,
        public readonly int $status = 200,
        public readonly string $contentType = 'text/plain; charset=utf-8',
    ) {
    }

    public static function plain(string $body, int $status = 200): self
    {
        return new self($body, $status, 'text/plain; charset=utf-8');
    }

    public static function xml(string $body, int $status = 200): self
    {
        return new self($body, $status, 'application/xml; charset=utf-8');
    }

    public function withCookie(string $name, string $value): self
    {
        $this->cookies[] = ['name' => $name, 'value' => $value];
        return $this;
    }

    /**
     * Отправляет ответ клиенту (1С). Вызывайте один раз, из вашей точки входа.
     */
    public function send(): void
    {
        foreach ($this->cookies as $cookie) {
            setcookie($cookie['name'], $cookie['value'], 0, '/');
        }

        http_response_code($this->status);
        header('Content-Type: ' . $this->contentType);
        echo $this->body;
    }
}
