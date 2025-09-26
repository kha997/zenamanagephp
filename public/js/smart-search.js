/**
 * Smart Search Utility - Reusable intelligent search functionality
 * Provides consistent search behavior across all views
 */

class SmartSearch {
    /**
     * Enhanced intelligent search function
     * @param {Array} items - Array of items to search
     * @param {string} query - Search query
     * @param {Object} searchFields - Fields to search in
     * @returns {Array} Filtered items
     */
    static search(items, query, searchFields = {}) {
        if (!query || !query.trim()) return items;
        
        const searchTerm = query.toLowerCase().trim();
        
        return items.filter(item => {
            // Default search fields if none provided
            const fields = searchFields.fields || ['name', 'title', 'description'];
            const tagsField = searchFields.tagsField || 'tags';
            const projectField = searchFields.projectField || 'project_name';
            const assigneeField = searchFields.assigneeField || 'assignee';
            
            // Search in main fields
            for (const field of fields) {
                if (item[field] && item[field].toLowerCase().includes(searchTerm)) {
                    return true;
                }
            }
            
            // Search in project field
            if (projectField && item[projectField] && item[projectField].toLowerCase().includes(searchTerm)) {
                return true;
            }
            
            // Search in assignee field
            if (assigneeField && item[assigneeField] && item[assigneeField].toLowerCase().includes(searchTerm)) {
                return true;
            }
            
            // Search in tags (array)
            if (tagsField && item[tagsField] && Array.isArray(item[tagsField])) {
                if (item[tagsField].some(tag => tag.toLowerCase().includes(searchTerm))) {
                    return true;
                }
            }
            
            // Search in tags (string)
            if (tagsField && item[tagsField] && typeof item[tagsField] === 'string') {
                if (item[tagsField].toLowerCase().includes(searchTerm)) {
                    return true;
                }
            }
            
            // Search in nested project object
            if (item.project && typeof item.project === 'object') {
                if (item.project.name && item.project.name.toLowerCase().includes(searchTerm)) {
                    return true;
                }
                if (item.project.code && item.project.code.toLowerCase().includes(searchTerm)) {
                    return true;
                }
            }
            
            return false;
        });
    }
    
    /**
     * Get search configuration for different entity types
     */
    static getSearchConfig(entityType) {
        const configs = {
            tasks: {
                fields: ['name', 'description'],
                tagsField: 'tags',
                projectField: 'project_name',
                assigneeField: 'assignee'
            },
            projects: {
                fields: ['name', 'description', 'code'],
                tagsField: 'tags',
                projectField: null,
                assigneeField: 'pm'
            },
            documents: {
                fields: ['title', 'description', 'filename'],
                tagsField: 'tags',
                projectField: 'project_name',
                assigneeField: 'uploaded_by'
            },
            invitations: {
                fields: ['email', 'role', 'status'],
                tagsField: null,
                projectField: null,
                assigneeField: 'invited_by'
            },
            templates: {
                fields: ['name', 'description', 'category'],
                tagsField: 'tags',
                projectField: null,
                assigneeField: 'created_by'
            }
        };
        
        return configs[entityType] || configs.tasks;
    }
    
    /**
     * Enhanced filter function with debug logging
     * @param {Array} items - Items to filter
     * @param {string} query - Search query
     * @param {string} entityType - Type of entity (tasks, projects, etc.)
     * @param {boolean} debug - Enable debug logging
     * @returns {Array} Filtered items
     */
    static filterWithDebug(items, query, entityType, debug = false) {
        const config = this.getSearchConfig(entityType);
        const filtered = this.search(items, query, config);
        
        if (debug) {
            console.log(`[${entityType}] Search query:`, query);
            console.log(`[${entityType}] Total items:`, items.length);
            console.log(`[${entityType}] Filtered items:`, filtered.length);
            console.log(`[${entityType}] Search config:`, config);
        }
        
        return filtered;
    }
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SmartSearch;
}

// Make available globally
window.SmartSearch = SmartSearch;
