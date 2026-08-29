<?php

declare(strict_types=1);

namespace CommerceML2\Auth;

use CommerceML2\Exception\AuthException;

/**
 * Проверяет логин/пароль (заданные в настройках выгрузки 1С) через HTTP Basic Auth
 * и держит авторизацию в обычной PHP-сессии. Подходит для большинства сценариев.
 */
final class BasicAuthSessionAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly string $login,
        private readonly string $password,
    ) {
    }

    public function authenticate(): ?SessionToken
    {
        $user = $_SERVER['PHP_AUTH_USER'] ?? null;
        $pass = $_SERVER['PHP_AUTH_PW'] ?? null;

        if ($user === null || $pass === null) {
            return null;
        }

        if (!hash_equals($this->login, $user) || !hash_equals($this->password, $pass)) {
            return null;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['commerceml2_authenticated'] = true;

        // cookie=false: сессия уже установлена стандартным механизмом PHP,
        // отдельную cookie через Response ставить не нужно.
        return new SessionToken(session_name(), session_id(), cookie: false);
    }

    public function verify(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // 1С присылает PHPSESSID как GET/Cookie параметр с именем из checkauth-ответа —
            // штатный session_start() подхватит его из cookie автоматически.
            session_start();
        }

        if (empty($_SESSION['commerceml2_authenticated'])) {
            throw new AuthException('Session is not authenticated, call checkauth first');
        }
    }
}
