<?php declare(strict_types=1);

namespace App\View\Components;

use App\Services\SidebarService;
use App\Services\BadgeService;
use Illuminate\View\Component;
use Illuminate\View\View;

class Sidebar extends Component
{
    public array $sidebarConfig;
    public ?string $currentRoute;
    public ?string $currentRole;
    protected BadgeService $badgeService;

    /**
     * Create a new component instance.
     */
    public function __construct(
        SidebarService $sidebarService,
        BadgeService $badgeService,
        ?string $currentRoute = null,
        ?string $currentRole = null
    ) {
        $this->sidebarConfig = $sidebarService->getSidebarForUser();
        $this->currentRoute = $currentRoute ?? request()->route()?->getName();
        $this->currentRole = $currentRole;
        $this->badgeService = $badgeService;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.sidebar');
    }

    /**
     * Check if a route is active.
     */
    public function isActive(string $route): bool
    {
        return $this->currentRoute === $route;
    }

    /**
     * Check if a URL is active.
     */
    public function isUrlActive(string $url): bool
    {
        return request()->is(ltrim($url, '/'));
    }

    /**
     * Build URL for a sidebar item.
     */
    public function buildUrl(array $item): string
    {
        if (isset($item['href'])) {
            return $item['href'];
        }

        if (isset($item['to'])) {
            $url = $item['to'];
            
            if (isset($item['query']) && is_array($item['query'])) {
                $url .= '?' . http_build_query($item['query']);
            }
            
            return $url;
        }

        return '#';
    }

    /**
     * Get icon class for an item.
     */
    public function getIconClass(array $item): string
    {
        $icon = $item['icon'] ?? 'circle';
        return "fas fa-{$icon}";
    }

    /**
     * Check if item has children.
     */
    public function hasChildren(array $item): bool
    {
        return $item['type'] === 'group' && isset($item['children']) && !empty($item['children']);
    }

    /**
     * Get item CSS classes.
     */
    public function getItemClasses(array $item): string
    {
        $classes = ['sidebar-item'];
        
        if ($item['type'] === 'group') {
            $classes[] = 'sidebar-group';
        }
        
        if (isset($item['pinned']) && $item['pinned']) {
            $classes[] = 'sidebar-pinned';
        }
        
        return implode(' ', $classes);
    }

    /**
     * Get badge count for an item.
     */
    public function getBadgeCount(array $item): int
    {
        if (!isset($item['show_badge_from'])) {
            return 0;
        }

        return $this->badgeService->getBadgeCount($item['id']);
    }

    /**
     * Check if item should show badge.
     */
    public function shouldShowBadge(array $item): bool
    {
        return isset($item['show_badge_from']) && $this->getBadgeCount($item) > 0;
    }
}
