<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Base for expected business failures that map to a clean HTTP response. */
class DomainException extends RuntimeException
{
    public function __construct(
        string $message,
        protected int $status = 422,
        protected string $errorCode = 'domain_error',
        protected array $context = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => $this->errorCode,
            'context' => $this->context,
        ], $this->status);
    }
}
