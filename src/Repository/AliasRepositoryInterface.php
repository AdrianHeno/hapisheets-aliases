<?php

declare(strict_types=1);

namespace App\Repository;

interface AliasRepositoryInterface
{
    public function existsByLocalPart(string $localPart): bool;
}
