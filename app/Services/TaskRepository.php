<?php

namespace App\Services;

use Src\CoreProject\Models\Task;
use Illuminate\Database\Eloquent\Collection;

class TaskRepository
{
    /**
     * Get all tasks
     */
    public function getAll(): Collection
    {
        return Task::all();
    }

    /**
     * Get task by ID
     */
    public function getById(string $id): ?Task
    {
        return Task::find($id);
    }

    /**
     * Create new task
     */
    public function create(array $data): Task
    {
        return Task::create($data);
    }

    /**
     * Update task
     */
    public function update(string $id, array $data): bool
    {
        $task = $this->getById($id);
        if (!$task) {
            return false;
        }
        
        return $task->update($data);
    }

    /**
     * Delete task
     */
    public function delete(string $id): bool
    {
        $task = $this->getById($id);
        if (!$task) {
            return false;
        }
        
        return $task->delete();
    }
}
