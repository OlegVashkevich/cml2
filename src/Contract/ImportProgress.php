<?php

declare(strict_types=1);

namespace CommerceML2\Contract;

/**
 * Результат mode=import для одного файла.
 *
 * Протокол CommerceML 2 предусматривает, что 1С готова повторять запрос mode=import
 * с тем же filename несколько раз подряд (с паузами), пока сайт не ответит "success".
 * Пока сайт отвечает "progress" — 1С считает, что импорт ещё идёт, и присылает
 * тот же запрос снова.
 */
final class ImportProgress
{
    private function __construct(
        public readonly bool $done,
        public readonly ?string $note = null,
    ) {
    }

    public static function done(): self
    {
        return new self(true);
    }

    /** @param string|null $note необязательный текст (попадёт в тело ответа после "progress") */
    public static function inProgress(?string $note = null): self
    {
        return new self(false, $note);
    }
}
