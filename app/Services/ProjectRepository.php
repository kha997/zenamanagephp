<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepository
{
    /**
     * Get all projects
     */
    public function getAll(): Collection
    {
        return Project::all();
    }

    /**
     * Get project by ID
     */
    public function getById(int $id): ?Project
    {
        return Project::find($id);
    }

    /**
     * Create new project
     */
    public function create(array $data): Project
    {
        return Project::create($data);
    }

    /**
     * Update project
     */
    public function update(int $id, array $data): bool
    {
        $project = $this->getById($id);
        if (!$project) {
            return false;
        }
        
        return $project->update($data);
    }

    /**
     * Delete project
     */
    public function delete(int $id): bool
    {
        $project = $this->getById($id);
        if (!$project) {
            return false;
        }
        
        return $project->delete();
    }
}
