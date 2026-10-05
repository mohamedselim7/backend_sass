<?php

namespace App\Policies;

class BrandPolicy extends OwnedByUserPolicy
{
    protected string $ownerColumn = 'created_by';
}
