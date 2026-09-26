<?php
declare(strict_types=1);

namespace Core\Http;

final class HttpException extends \RuntimeException
{
    public function __construct(int $code, string $message = '')
    {
        parent::__construct($message, $code);
    }
}
