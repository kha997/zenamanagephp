<?php

namespace App\View\Components;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class RoleBadge extends Component
{
    public $role;
    public $user;

    /**
     * Create a new component instance.
     */
    public function __construct($role = null)
    {
        $this->user = Auth::user();
        $this->role = $role ?: $this->getUserRole();
    }

    /**
     * Get the user's primary role
     */
    private function getUserRole()
    {
        if (!$this->user) {
            return 'Guest';
        }

        // For now, return a default role
        
        return 'Project Manager';
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('components.role-badge');
    }
}