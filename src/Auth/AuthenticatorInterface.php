<?php

declare(strict_types=1);

namespace CommerceML2\Auth;

use CommerceML2\Exception\AuthException;

interface AuthenticatorInterface
{
    /**
     * Вызывается на mode=checkauth. Обычно логин/пароль уже проверены сервером
     * через HTTP Basic Auth (настраивается в .htaccess / nginx / настройках 1С),
     * поэтому здесь чаще всего просто нужно создать/вернуть сессию.
     *
     * Верните null, если считаете запрос неавторизованным.
     */
    public function authenticate(): ?SessionToken;

    /**
     * Вызывается на всех запросах, кроме checkauth. Должен бросить AuthException,
     * если сессия отсутствует/невалидна (например переданный PHPSESSID не найден).
     */
    public function verify(): void;
}
