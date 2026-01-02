<?php

/**
 * Button Inventory Generator
 * 
 * This script analyzes all Blade views and components to extract button/action elements
 * and generates a comprehensive CSV inventory for testing purposes.
 */

class ButtonInventoryGenerator
{
    private $viewsPath;
    private $componentsPath;
    private $routes;
    private $inventory = [];
    
    public function __construct()
    {
        $this->viewsPath = __DIR__ . "/resources/views";
        $this->componentsPath = __DIR__ . "/resources/views/components";
        $this->loadRoutes();
    }
    
    private function loadRoutes()
    {
        // Load web routes
        $webRoutes = file_get_contents(__DIR__ . "/routes/web.php");
        $apiRoutes = file_get_contents(__DIR__ . "/routes/api.php");
        
        $this->routes = [
            'web' => $webRoutes,
            'api' => $apiRoutes
        ];
    }
    
    public function generateInventory()
    {
        echo "Starting Button Inventory Generation...\n";
        
        // Scan all views
        $this->scanDirectory($this->viewsPath);
        
        // Scan components
        $this->scanDirectory($this->componentsPath);
        
        // Generate CSV
        $this->generateCSV();
        
        echo "Button Inventory generated successfully!\n";
        echo "Total buttons found: " . count($this->inventory) . "\n";
    }
    
    private function scanDirectory($path)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->analyzeView($file->getPathname());
            }
        }
    }
    
    private function analyzeView($filePath)
    {
        $content = file_get_contents($filePath);
        $relativePath = str_replace($this->viewsPath, '', $filePath);
        $viewName = str_replace(['/', '.blade.php'], ['.', ''], ltrim($relativePath, '/'));
        
        // Extract buttons and interactive elements
        $this->extractButtons($content, $viewName, $filePath);
        $this->extractLinks($content, $viewName, $filePath);
        $this->extractForms($content, $viewName, $filePath);
        $this->extractAlpineActions($content, $viewName, $filePath);
        $this->extractCustomComponents($content, $viewName, $filePath);
    }
    
    private function extractButtons($content, $viewName, $filePath)
    {
        // Match button elements
        preg_match_all('/<button[^>]*>(.*?)<\/button>/s', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $buttonHtml = $match[0];
            $buttonText = strip_tags($match[1]);
            
            $this->addToInventory([
                'view_path' => $viewName,
                'view_name' => $viewName,
                'dom_selector' => $this->generateSelector($buttonHtml),
                'label_or_icon' => $buttonText ?: $this->extractIcon($buttonHtml),
                'type' => 'button',
                'trigger' => $this->extractTrigger($buttonHtml),
                'route_or_url' => $this->extractRoute($buttonHtml),
                'http_method' => $this->extractMethod($buttonHtml),
                'policy_middleware' => $this->extractPolicy($viewName),
                'required_role' => $this->extractRequiredRole($viewName),
                'tenant_scope' => 'yes',
                'expected_result' => $this->determineExpectedResult($buttonHtml),
                'side_effects' => $this->determineSideEffects($buttonHtml),
                'loading_disabled_state' => $this->hasLoadingState($buttonHtml),
                'error_states' => $this->determineErrorStates($buttonHtml),
                'notes' => $this->generateNotes($buttonHtml, $viewName)
            ]);
        }
    }
    
    private function extractLinks($content, $viewName, $filePath)
    {
        // Match anchor elements with href
        preg_match_all('/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/s', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $linkHtml = $match[0];
            $href = $match[1];
            $linkText = strip_tags($match[2]);
            
            // Skip external links and anchors
            if (strpos($href, 'http') === 0 || strpos($href, '#') === 0) {
                continue;
            }
            
            $this->addToInventory([
                'view_path' => $viewName,
                'view_name' => $viewName,
                'dom_selector' => $this->generateSelector($linkHtml),
                'label_or_icon' => $linkText ?: $this->extractIcon($linkHtml),
                'type' => 'link',
                'trigger' => 'href',
                'route_or_url' => $href,
                'http_method' => 'GET',
                'policy_middleware' => $this->extractPolicy($viewName),
                'required_role' => $this->extractRequiredRole($viewName),
                'tenant_scope' => 'yes',
                'expected_result' => 'navigation',
                'side_effects' => 'none',
                'loading_disabled_state' => 'no',
                'error_states' => '404,403',
                'notes' => $this->generateNotes($linkHtml, $viewName)
            ]);
        }
    }
    
    private function extractForms($content, $viewName, $filePath)
    {
        // Match form elements
        preg_match_all('/<form[^>]*>(.*?)<\/form>/s', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $formHtml = $match[0];
            $formContent = $match[1];
            
            // Extract submit buttons within forms
            preg_match_all('/<button[^>]*type=["\']submit["\'][^>]*>(.*?)<\/button>/s', $formContent, $submitMatches, PREG_SET_ORDER);
            
            foreach ($submitMatches as $submitMatch) {
                $buttonHtml = $submitMatch[0];
                $buttonText = strip_tags($submitMatch[1]);
                
                $this->addToInventory([
                    'view_path' => $viewName,
                    'view_name' => $viewName,
                    'dom_selector' => $this->generateSelector($buttonHtml),
                    'label_or_icon' => $buttonText ?: $this->extractIcon($buttonHtml),
                    'type' => 'form-submit',
                    'trigger' => 'form',
                    'route_or_url' => $this->extractFormAction($formHtml),
                    'http_method' => $this->extractFormMethod($formHtml),
                    'policy_middleware' => $this->extractPolicy($viewName),
                    'required_role' => $this->extractRequiredRole($viewName),
                    'tenant_scope' => 'yes',
                    'expected_result' => 'form_submission',
                    'side_effects' => 'data_change',
                    'loading_disabled_state' => 'yes',
                    'error_states' => '422,500,403',
                    'notes' => $this->generateNotes($buttonHtml, $viewName)
                ]);
            }
        }
    }
    
    private function extractAlpineActions($content, $viewName, $filePath)
    {
        // Match Alpine.js actions
        preg_match_all('/@click=["\']([^"\']*)["\']/', $content, $matches, PREG_SET_ORDER);
        preg_match_all('/x-on:click=["\']([^"\']*)["\']/', $content, $matches2, PREG_SET_ORDER);
        
        $allMatches = array_merge($matches, $matches2);
        
        foreach ($allMatches as $match) {
            $action = $match[1];
            $elementHtml = $this->findElementWithAction($content, $match[0]);
            
            $this->addToInventory([
                'view_path' => $viewName,
                'view_name' => $viewName,
                'dom_selector' => $this->generateSelector($elementHtml),
                'label_or_icon' => $this->extractElementText($elementHtml),
                'type' => 'alpine-action',
                'trigger' => 'js',
                'route_or_url' => $this->extractAlpineRoute($action),
                'http_method' => $this->extractAlpineMethod($action),
                'policy_middleware' => $this->extractPolicy($viewName),
                'required_role' => $this->extractRequiredRole($viewName),
                'tenant_scope' => 'yes',
                'expected_result' => 'js_action',
                'side_effects' => 'ui_change',
                'loading_disabled_state' => 'yes',
                'error_states' => 'js_error',
                'notes' => "Alpine.js action: {$action}"
            ]);
        }
    }
    
    private function extractCustomComponents($content, $viewName, $filePath)
    {
        // Match custom components
        preg_match_all('/<x-([^>]*?)>/', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $componentHtml = $match[0];
            $componentName = $match[1];
            
            // Extract component attributes that might be actions
            if (preg_match('/action=["\']([^"\']*)["\']/', $componentHtml, $actionMatch)) {
                $action = $actionMatch[1];
                
                $this->addToInventory([
                    'view_path' => $viewName,
                    'view_name' => $viewName,
                    'dom_selector' => "x-{$componentName}",
                    'label_or_icon' => $componentName,
                    'type' => 'custom-component',
                    'trigger' => 'component',
                    'route_or_url' => $action,
                    'http_method' => 'POST',
                    'policy_middleware' => $this->extractPolicy($viewName),
                    'required_role' => $this->extractRequiredRole($viewName),
                    'tenant_scope' => 'yes',
                    'expected_result' => 'component_action',
                    'side_effects' => 'component_change',
                    'loading_disabled_state' => 'yes',
                    'error_states' => 'component_error',
                    'notes' => "Custom component: x-{$componentName}"
                ]);
            }
        }
    }
    
    private function addToInventory($data)
    {
        // Avoid duplicates
        $key = $data['view_path'] . '|' . $data['dom_selector'] . '|' . $data['type'];
        if (!isset($this->inventory[$key])) {
            $this->inventory[$key] = $data;
        }
    }
    
    private function generateSelector($html)
    {
        // Extract id, class, or generate a basic selector
        if (preg_match('/id=["\']([^"\']*)["\']/', $html, $matches)) {
            return '#' . $matches[1];
        }
        
        if (preg_match('/class=["\']([^"\']*)["\']/', $html, $matches)) {
            $classes = explode(' ', $matches[1]);
            return '.' . $classes[0];
        }
        
        // Generate selector based on element type
        if (preg_match('/<(\w+)/', $html, $matches)) {
            return $matches[1];
        }
        
        return 'unknown';
    }
    
    private function extractIcon($html)
    {
        // Look for common icon patterns
        if (preg_match('/<i[^>]*class=["\']([^"\']*)["\'][^>]*><\/i>/', $html, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/<svg[^>]*>(.*?)<\/svg>/s', $html, $matches)) {
            return 'svg-icon';
        }
        
        return '';
    }
    
    private function extractTrigger($html)
    {
        if (strpos($html, '@click') !== false || strpos($html, 'x-on:click') !== false) {
            return 'js';
        }
        
        if (strpos($html, 'type="submit"') !== false) {
            return 'form';
        }
        
        return 'click';
    }
    
    private function extractRoute($html)
    {
        // Look for route() calls
        if (preg_match('/route\(["\']([^"\']*)["\']/', $html, $matches)) {
            return $matches[1];
        }
        
        // Look for href attributes
        if (preg_match('/href=["\']([^"\']*)["\']/', $html, $matches)) {
            return $matches[1];
        }
        
        return '';
    }
    
    private function extractMethod($html)
    {
        if (strpos($html, 'method="POST"') !== false) {
            return 'POST';
        }
        
        if (strpos($html, 'method="PUT"') !== false) {
            return 'PUT';
        }
        
        if (strpos($html, 'method="DELETE"') !== false) {
            return 'DELETE';
        }
        
        return 'GET';
    }
    
    private function extractPolicy($viewName)
    {
        // Map view names to policies based on common patterns
        $policyMap = [
            'admin' => 'admin',
            'projects' => 'project',
            'tasks' => 'task',
            'documents' => 'document',
            'team' => 'team',
            'templates' => 'template'
        ];
        
        foreach ($policyMap as $pattern => $policy) {
            if (strpos($viewName, $pattern) !== false) {
                return $policy;
            }
        }
        
        return 'default';
    }
    
    private function extractRequiredRole($viewName)
    {
        // Map view names to required roles
        $roleMap = [
            'admin' => 'super_admin,admin',
            'projects' => 'project_manager,admin',
            'tasks' => 'project_manager,designer,site_engineer',
            'documents' => 'project_manager,designer',
            'team' => 'admin,project_manager',
            'templates' => 'admin,project_manager'
        ];
        
        foreach ($roleMap as $pattern => $roles) {
            if (strpos($viewName, $pattern) !== false) {
                return $roles;
            }
        }
        
        return 'authenticated';
    }
    
    private function determineExpectedResult($html)
    {
        if (strpos($html, 'delete') !== false || strpos($html, 'destroy') !== false) {
            return 'deletion';
        }
        
        if (strpos($html, 'create') !== false || strpos($html, 'store') !== false) {
            return 'creation';
        }
        
        if (strpos($html, 'edit') !== false || strpos($html, 'update') !== false) {
            return 'update';
        }
        
        return 'action';
    }
    
    private function determineSideEffects($html)
    {
        if (strpos($html, 'delete') !== false) {
            return 'data_deletion';
        }
        
        if (strpos($html, 'create') !== false || strpos($html, 'store') !== false) {
            return 'data_creation';
        }
        
        if (strpos($html, 'edit') !== false || strpos($html, 'update') !== false) {
            return 'data_update';
        }
        
        return 'none';
    }
    
    private function hasLoadingState($html)
    {
        return strpos($html, 'loading') !== false || strpos($html, 'disabled') !== false ? 'yes' : 'no';
    }
    
    private function determineErrorStates($html)
    {
        $errors = ['403', '404'];
        
        if (strpos($html, 'form') !== false) {
            $errors[] = '422';
        }
        
        if (strpos($html, 'delete') !== false) {
            $errors[] = '500';
        }
        
        return implode(',', $errors);
    }
    
    private function generateNotes($html, $viewName)
    {
        $notes = [];
        
        if (strpos($html, 'csrf') !== false) {
            $notes[] = 'CSRF protected';
        }
        
        if (strpos($html, 'confirm') !== false) {
            $notes[] = 'Requires confirmation';
        }
        
        if (strpos($html, 'modal') !== false) {
            $notes[] = 'Opens modal';
        }
        
        return implode('; ', $notes);
    }
    
    private function extractFormAction($html)
    {
        if (preg_match('/action=["\']([^"\']*)["\']/', $html, $matches)) {
            return $matches[1];
        }
        
        return '';
    }
    
    private function extractFormMethod($html)
    {
        if (preg_match('/method=["\']([^"\']*)["\']/', $html, $matches)) {
            return strtoupper($matches[1]);
        }
        
        return 'POST';
    }
    
    private function findElementWithAction($content, $action)
    {
        // Find the element containing the Alpine action
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (strpos($line, $action) !== false) {
                return $line;
            }
        }
        
        return '';
    }
    
    private function extractElementText($html)
    {
        return strip_tags($html);
    }
    
    private function extractAlpineRoute($action)
    {
        // Look for route patterns in Alpine actions
        if (preg_match('/route\(["\']([^"\']*)["\']/', $action, $matches)) {
            return $matches[1];
        }
        
        return '';
    }
    
    private function extractAlpineMethod($action)
    {
        if (strpos($action, 'POST') !== false) {
            return 'POST';
        }
        
        if (strpos($action, 'PUT') !== false) {
            return 'PUT';
        }
        
        if (strpos($action, 'DELETE') !== false) {
            return 'DELETE';
        }
        
        return 'GET';
    }
    
    private function generateCSV()
    {
        $csvPath = __DIR__ . "/docs/testing/button-inventory.csv";
        
        if (!is_dir(dirname($csvPath))) { mkdir(dirname($csvPath), 0777, true); }
        $file = fopen($csvPath, 'w');
        
        // Write header
        fputcsv($file, [
            'view_path',
            'view_name', 
            'dom_selector',
            'label_or_icon',
            'type',
            'trigger',
            'route_or_url',
            'http_method',
            'policy_middleware',
            'required_role',
            'tenant_scope',
            'expected_result',
            'side_effects',
            'loading_disabled_state',
            'error_states',
            'notes'
        ]);
        
        // Write data
        foreach ($this->inventory as $item) {
            fputcsv($file, [
                $item['view_path'],
                $item['view_name'],
                $item['dom_selector'],
                $item['label_or_icon'],
                $item['type'],
                $item['trigger'],
                $item['route_or_url'],
                $item['http_method'],
                $item['policy_middleware'],
                $item['required_role'],
                $item['tenant_scope'],
                $item['expected_result'],
                $item['side_effects'],
                $item['loading_disabled_state'],
                $item['error_states'],
                $item['notes']
            ]);
        }
        
        fclose($file);
        
        echo "CSV generated at: {$csvPath}\n";
    }
}

// Run the generator
$generator = new ButtonInventoryGenerator();
$generator->generateInventory();
