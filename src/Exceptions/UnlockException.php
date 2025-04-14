<?php

namespace Procentr\FileLockManager\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception that occurs when file unlocking errors happen.
 */
class UnlockException extends RuntimeException
{
    /**
     * Constructor.
     *
     * @param string $message Error message.
     * @param int $code Error code (default 0).
     * @param Throwable|null $previous Previous exception, if any.
     */
    public function __construct(string $message = "File not found", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
