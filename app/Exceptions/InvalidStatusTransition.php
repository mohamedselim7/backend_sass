<?php

namespace App\Exceptions;

class InvalidStatusTransition extends DomainException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(
            "لا يمكن تغيير الحالة من {$from} إلى {$to}.",
            409,
            'invalid_status_transition',
            ['from' => $from, 'to' => $to],
        );
    }
}
