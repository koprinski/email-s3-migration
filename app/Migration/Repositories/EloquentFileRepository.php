<?php

namespace App\Migration\Repositories;

use App\Migration\Contracts\FileRepositoryInterface;
use App\Migration\DTO\FileData;
use App\Models\File;

final class EloquentFileRepository implements FileRepositoryInterface
{
    public function resolve(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = File::query()->whereIn('id', $ids)->get()->keyBy('id');

        $resolved = [];
        foreach ($ids as $id) {
            $file = $rows->get($id);
            if ($file !== null) {
                $resolved[] = new FileData($file->id, $file->name, $file->path, (int) $file->size, $file->type);
            }
        }

        return $resolved;
    }
}
