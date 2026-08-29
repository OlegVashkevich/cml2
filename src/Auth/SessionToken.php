<?php

declare(strict_types=1);

namespace CommerceML2\Auth;

/**
 * Пара имя/значение, которую 1С должна присылать в последующих запросах
 * (обычно это либо PHP-сессия PHPSESSID, либо собственный токен).
 */
final class SessionToken
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
        /** Если true — Response выставит cookie сам. Если вы уже используете session_start(),
         *  можно поставить false и вернуть name=PHPSESSID / value=session_id() без лишней cookie. */
        public readonly bool $cookie = true,
    ) {
    }
}
