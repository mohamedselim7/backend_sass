<?php

namespace App\Exceptions;

class InsufficientCreditsException extends DomainException
{
    public function __construct(int $required, int $available)
    {
        parent::__construct(
            'لا يوجد رصيد كافٍ لإتمام هذه العملية.',
            402,
            'insufficient_credits',
            ['required' => $required, 'available' => $available],
        );
    }
}
