<?php

namespace ElChoco\AdminConsole;

require_once __DIR__.'/ConsoleMessage.php';

/** Preserve the command policy's InvalidArgumentException contract. */
class CommandMessage extends \InvalidArgumentException
{
    public string $translationKey;
    public array $translationParameters;

    public function __construct(string $key, string $fallback, array $parameters = [])
    {
        $this->translationKey = 'el-choco-admin-console.admin.'.$key;
        $this->translationParameters = $parameters;
        parent::__construct(ConsoleMessage::format($fallback, $parameters));
    }
}
