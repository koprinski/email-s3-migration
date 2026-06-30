<?php

namespace App\Migration\Contracts;

interface FileRepositoryInterface
{
    /**
     * Resolve files rows for the given ids, order-preserving against $ids and silently dropping ids with no matching row (the caller decides the policy).
     */
    public function resolve(array $ids): array;
}
