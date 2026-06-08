<?php

namespace Rekuest\ArtifactDeployer\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Domain exception carrying the HTTP status code that the trigger (HTTP/CLI)
 * should surface. Used to map pipeline failures to explicit response codes
 * without leaking stack traces.
 */
class DeployException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 500,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function unprocessable(string $message): self
    {
        return new self($message, 422);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }

    public static function server(string $message, ?Throwable $previous = null): self
    {
        return new self($message, 500, $previous);
    }
}
