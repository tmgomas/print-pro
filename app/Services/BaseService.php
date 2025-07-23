<?php

namespace App\Services;

use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseService
{
    protected BaseRepository $repository;

    public function __construct(BaseRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Create a new record with transaction
     */
    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            return $this->repository->create($data);
        });
    }

    /**
     * Update a record with transaction
     */
    public function update(int $id, array $data): bool
    {
        return DB::transaction(function () use ($id, $data) {
            return $this->repository->update($id, $data);
        });
    }

    /**
     * Delete a record with transaction
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->repository->delete($id);
        });
    }

    /**
     * Handle service exceptions
     */
    protected function handleException(\Exception $e, string $action): void
    {
        Log::error("Service error during {$action}: " . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
        throw $e;
    }
}