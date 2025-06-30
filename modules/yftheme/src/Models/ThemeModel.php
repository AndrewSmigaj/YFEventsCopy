<?php

namespace YFEvents\Modules\YFTheme\Models;

use PDO;
use Exception;

/**
 * Theme Model
 * Manages theme variables, presets, and customizations
 */
class ThemeModel
{
    private PDO $db;
    private array $cache = [];
    private bool $cacheEnabled = true;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get all theme variables grouped by category
     */
    public function getVariablesByCategory(bool $includeAdvanced = false): array
    {
        $sql = "
            SELECT 
                c.id as category_id,
                c.name as category_name,
                c.display_name as category_display_name,
                c.description as category_description,
                c.icon as category_icon,
                v.id,
                v.name,
                v.css_variable,
                v.display_name,
                v.description,
                v.type,
                v.default_value,
                v.current_value,
                v.options,
                v.constraints,
                v.preview_element,
                v.is_advanced
            FROM theme_categories c
            LEFT JOIN theme_variables v ON c.id = v.category_id
        ";
        
        if (!$includeAdvanced) {
            $sql .= " AND v.is_advanced = FALSE";
        }
        
        $sql .= " ORDER BY c.sort_order, v.sort_order";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $categories = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categoryId = $row['category_id'];
            
            if (!isset($categories[$categoryId])) {
                $categories[$categoryId] = [
                    'id' => $categoryId,
                    'name' => $row['category_name'],
                    'display_name' => $row['category_display_name'],
                    'description' => $row['category_description'],
                    'icon' => $row['category_icon'],
                    'variables' => []
                ];
            }
            
            if ($row['id']) {
                $categories[$categoryId]['variables'][] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'css_variable' => $row['css_variable'],
                    'display_name' => $row['display_name'],
                    'description' => $row['description'],
                    'type' => $row['type'],
                    'default_value' => $row['default_value'],
                    'current_value' => $row['current_value'] ?? $row['default_value'],
                    'options' => json_decode($row['options'], true),
                    'constraints' => json_decode($row['constraints'], true),
                    'preview_element' => $row['preview_element'],
                    'is_advanced' => (bool)$row['is_advanced']
                ];
            }
        }
        
        return array_values($categories);
    }

    /**
     * Get all theme presets
     */
    public function getPresets(): array
    {
        $sql = "
            SELECT 
                id, name, display_name, description, 
                thumbnail_url, is_active, is_default,
                created_at, updated_at
            FROM theme_presets
            ORDER BY is_default DESC, display_name
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get preset by ID with all variables
     */
    public function getPreset(int $presetId): ?array
    {
        $sql = "SELECT * FROM theme_presets WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$presetId]);
        $preset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$preset) {
            return null;
        }
        
        // Get preset variables
        $sql = "
            SELECT 
                v.id, v.name, v.css_variable, v.type,
                pv.value
            FROM theme_variables v
            JOIN theme_preset_variables pv ON v.id = pv.variable_id
            WHERE pv.preset_id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$presetId]);
        
        $variables = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $variables[$row['name']] = [
                'id' => $row['id'],
                'css_variable' => $row['css_variable'],
                'type' => $row['type'],
                'value' => $row['value']
            ];
        }
        
        $preset['variables'] = $variables;
        
        return $preset;
    }

    /**
     * Get active preset
     */
    public function getActivePreset(): ?array
    {
        $sql = "SELECT * FROM theme_presets WHERE is_active = TRUE LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $preset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $preset ? $this->getPreset($preset['id']) : null;
    }

    /**
     * Update theme variable value
     */
    public function updateVariable(int $variableId, string $value, int $userId): bool
    {
        try {
            $this->db->beginTransaction();
            
            // Get old value for history
            $sql = "SELECT name, current_value FROM theme_variables WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$variableId]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldData) {
                throw new Exception("Variable not found");
            }
            
            // Update variable
            $sql = "UPDATE theme_variables SET current_value = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$value, $variableId]);
            
            // Log history
            $sql = "
                INSERT INTO theme_history 
                (variable_id, old_value, new_value, change_type, changed_by)
                VALUES (?, ?, ?, 'variable_update', ?)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $variableId,
                $oldData['current_value'],
                $value,
                $userId
            ]);
            
            $this->db->commit();
            
            // Clear cache
            $this->clearCache();
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to update theme variable: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update multiple variables at once
     */
    public function updateVariables(array $variables, int $userId, string $reason = null): bool
    {
        try {
            $this->db->beginTransaction();
            
            foreach ($variables as $variableId => $value) {
                // Get old value
                $sql = "SELECT current_value FROM theme_variables WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$variableId]);
                $oldValue = $stmt->fetchColumn();
                
                // Update variable
                $sql = "UPDATE theme_variables SET current_value = ? WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value, $variableId]);
                
                // Log history
                $sql = "
                    INSERT INTO theme_history 
                    (variable_id, old_value, new_value, change_type, changed_by, change_reason)
                    VALUES (?, ?, ?, 'bulk_update', ?, ?)
                ";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $variableId,
                    $oldValue,
                    $value,
                    $userId,
                    $reason
                ]);
            }
            
            $this->db->commit();
            
            // Clear cache
            $this->clearCache();
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to update theme variables: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply a preset
     */
    public function applyPreset(int $presetId, int $userId): bool
    {
        try {
            $this->db->beginTransaction();
            
            // Get preset
            $preset = $this->getPreset($presetId);
            if (!$preset) {
                throw new Exception("Preset not found");
            }
            
            // Deactivate current preset
            $sql = "UPDATE theme_presets SET is_active = FALSE WHERE is_active = TRUE";
            $this->db->exec($sql);
            
            // Activate new preset
            $sql = "UPDATE theme_presets SET is_active = TRUE WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$presetId]);
            
            // Apply preset values to current values
            $sql = "
                UPDATE theme_variables v
                JOIN theme_preset_variables pv ON v.id = pv.variable_id
                SET v.current_value = pv.value
                WHERE pv.preset_id = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$presetId]);
            
            // Log history
            $sql = "
                INSERT INTO theme_history 
                (preset_id, change_type, changed_by, change_reason, metadata)
                VALUES (?, 'preset_change', ?, 'Applied preset', ?)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $presetId,
                $userId,
                json_encode(['preset_name' => $preset['name']])
            ]);
            
            $this->db->commit();
            
            // Clear cache
            $this->clearCache();
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to apply preset: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create new preset from current values
     */
    public function createPreset(array $data, int $userId): ?int
    {
        try {
            $this->db->beginTransaction();
            
            // Create preset
            $sql = "
                INSERT INTO theme_presets 
                (name, display_name, description, thumbnail_url, created_by)
                VALUES (?, ?, ?, ?, ?)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['display_name'],
                $data['description'] ?? null,
                $data['thumbnail_url'] ?? null,
                $userId
            ]);
            
            $presetId = $this->db->lastInsertId();
            
            // Copy current values to preset
            $sql = "
                INSERT INTO theme_preset_variables (preset_id, variable_id, value)
                SELECT ?, id, COALESCE(current_value, default_value)
                FROM theme_variables
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$presetId]);
            
            // Log history
            $sql = "
                INSERT INTO theme_history 
                (preset_id, change_type, changed_by, metadata)
                VALUES (?, 'preset_create', ?, ?)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $presetId,
                $userId,
                json_encode($data)
            ]);
            
            $this->db->commit();
            
            return $presetId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to create preset: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate CSS from current theme values
     */
    public function generateCSS(array $scope = []): string
    {
        $cacheKey = 'css_' . md5(json_encode($scope));
        
        if ($this->cacheEnabled && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        // Get all variables with overrides
        $variables = $this->getVariablesWithOverrides($scope);
        
        // Start CSS
        $css = "/* YFTheme Generated CSS - " . date('Y-m-d H:i:s') . " */\n\n";
        
        // Root variables
        $css .= ":root {\n";
        foreach ($variables as $var) {
            if (!isset($var['scope_type'])) {
                $value = $var['current_value'] ?? $var['default_value'];
                $css .= "    {$var['css_variable']}: {$value};\n";
            }
        }
        $css .= "}\n\n";
        
        // Scoped overrides
        $scopedVars = array_filter($variables, function($var) {
            return isset($var['scope_type']);
        });
        
        if (!empty($scopedVars)) {
            // Group by scope
            $byScope = [];
            foreach ($scopedVars as $var) {
                $selector = $this->getScopeSelector($var['scope_type'], $var['scope_value']);
                if (!isset($byScope[$selector])) {
                    $byScope[$selector] = [];
                }
                $byScope[$selector][] = $var;
            }
            
            // Generate scoped CSS
            foreach ($byScope as $selector => $vars) {
                $css .= "{$selector} {\n";
                foreach ($vars as $var) {
                    $css .= "    {$var['css_variable']}: {$var['override_value']};\n";
                }
                $css .= "}\n\n";
            }
        }
        
        // Add utility classes
        $css .= $this->generateUtilityClasses($variables);
        
        if ($this->cacheEnabled) {
            $this->cache[$cacheKey] = $css;
        }
        
        return $css;
    }

    /**
     * Get variables with overrides for specific scope
     */
    private function getVariablesWithOverrides(array $scope): array
    {
        // Base query for all variables
        $sql = "
            SELECT 
                v.id, v.name, v.css_variable, v.type,
                v.default_value, v.current_value
            FROM theme_variables v
            ORDER BY v.id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $variables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get overrides if scope provided
        if (!empty($scope)) {
            $conditions = [];
            $params = [];
            
            foreach ($scope as $type => $value) {
                $conditions[] = "(o.scope_type = ? AND o.scope_value = ?)";
                $params[] = $type;
                $params[] = $value;
            }
            
            $sql = "
                SELECT 
                    o.variable_id, o.scope_type, o.scope_value, 
                    o.value as override_value, o.priority
                FROM theme_variable_overrides o
                WHERE o.is_active = TRUE 
                AND (" . implode(' OR ', $conditions) . ")
                ORDER BY o.priority DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $overrides = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Apply overrides
            foreach ($overrides as $override) {
                foreach ($variables as &$var) {
                    if ($var['id'] == $override['variable_id']) {
                        $var['scope_type'] = $override['scope_type'];
                        $var['scope_value'] = $override['scope_value'];
                        $var['override_value'] = $override['override_value'];
                        break;
                    }
                }
            }
        }
        
        return $variables;
    }

    /**
     * Get CSS selector for scope
     */
    private function getScopeSelector(string $type, string $value): string
    {
        switch ($type) {
            case 'page':
                return "body[data-page=\"{$value}\"]";
            case 'section':
                return ".section-{$value}";
            case 'module':
                return ".module-{$value}";
            case 'user_group':
                return "body.user-group-{$value}";
            default:
                return ".{$value}";
        }
    }

    /**
     * Generate utility classes based on theme variables
     */
    private function generateUtilityClasses(array $variables): string
    {
        $css = "/* Utility Classes */\n\n";
        
        // Color utilities
        foreach ($variables as $var) {
            if ($var['type'] === 'color' && strpos($var['name'], '-color') !== false) {
                $name = str_replace('-color', '', $var['name']);
                $value = $var['current_value'] ?? $var['default_value'];
                
                $css .= ".text-{$name} { color: {$value}; }\n";
                $css .= ".bg-{$name} { background-color: {$value}; }\n";
                $css .= ".border-{$name} { border-color: {$value}; }\n";
            }
        }
        
        $css .= "\n";
        
        // Spacing utilities
        $spacingVars = ['small', 'medium', 'large', 'xlarge'];
        foreach ($spacingVars as $size) {
            foreach ($variables as $var) {
                if ($var['name'] === "spacing-{$size}") {
                    $value = $var['current_value'] ?? $var['default_value'];
                    
                    $css .= ".p-{$size} { padding: {$value}; }\n";
                    $css .= ".m-{$size} { margin: {$value}; }\n";
                    $css .= ".mt-{$size} { margin-top: {$value}; }\n";
                    $css .= ".mb-{$size} { margin-bottom: {$value}; }\n";
                    $css .= ".ml-{$size} { margin-left: {$value}; }\n";
                    $css .= ".mr-{$size} { margin-right: {$value}; }\n";
                    $css .= ".pt-{$size} { padding-top: {$value}; }\n";
                    $css .= ".pb-{$size} { padding-bottom: {$value}; }\n";
                    $css .= ".pl-{$size} { padding-left: {$value}; }\n";
                    $css .= ".pr-{$size} { padding-right: {$value}; }\n";
                }
            }
        }
        
        return $css;
    }

    /**
     * Export theme as JSON
     */
    public function exportTheme(int $presetId = null): array
    {
        if ($presetId) {
            $preset = $this->getPreset($presetId);
            if (!$preset) {
                throw new Exception("Preset not found");
            }
            
            return [
                'version' => '1.0',
                'exported_at' => date('Y-m-d H:i:s'),
                'preset' => $preset,
                'variables' => $preset['variables']
            ];
        } else {
            // Export current theme
            $variables = [];
            $sql = "SELECT name, css_variable, type, current_value FROM theme_variables";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $variables[$row['name']] = [
                    'css_variable' => $row['css_variable'],
                    'type' => $row['type'],
                    'value' => $row['current_value']
                ];
            }
            
            return [
                'version' => '1.0',
                'exported_at' => date('Y-m-d H:i:s'),
                'variables' => $variables
            ];
        }
    }

    /**
     * Import theme from JSON
     */
    public function importTheme(array $data, int $userId): bool
    {
        try {
            $this->db->beginTransaction();
            
            if (isset($data['preset'])) {
                // Import as new preset
                $presetData = $data['preset'];
                $presetData['name'] .= '_imported_' . time();
                
                $presetId = $this->createPreset($presetData, $userId);
                if (!$presetId) {
                    throw new Exception("Failed to create preset");
                }
                
                // Update preset variables
                if (isset($data['variables'])) {
                    foreach ($data['variables'] as $varName => $varData) {
                        $sql = "
                            UPDATE theme_preset_variables pv
                            JOIN theme_variables v ON pv.variable_id = v.id
                            SET pv.value = ?
                            WHERE pv.preset_id = ? AND v.name = ?
                        ";
                        
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([
                            $varData['value'],
                            $presetId,
                            $varName
                        ]);
                    }
                }
            } else {
                // Import as current theme
                foreach ($data['variables'] as $varName => $varData) {
                    $sql = "UPDATE theme_variables SET current_value = ? WHERE name = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$varData['value'], $varName]);
                }
            }
            
            $this->db->commit();
            
            // Clear cache
            $this->clearCache();
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to import theme: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get theme history
     */
    public function getHistory(int $limit = 50): array
    {
        $sql = "
            SELECT 
                h.*,
                v.name as variable_name,
                v.display_name as variable_display_name,
                p.name as preset_name,
                p.display_name as preset_display_name,
                u.username as changed_by_username
            FROM theme_history h
            LEFT JOIN theme_variables v ON h.variable_id = v.id
            LEFT JOIN theme_presets p ON h.preset_id = p.id
            LEFT JOIN auth_users u ON h.changed_by = u.id
            ORDER BY h.created_at DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Rollback to specific history point
     */
    public function rollbackToHistory(int $historyId, int $userId): bool
    {
        try {
            $sql = "SELECT * FROM theme_history WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$historyId]);
            $history = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$history) {
                throw new Exception("History entry not found");
            }
            
            if ($history['change_type'] === 'variable_update' && $history['variable_id']) {
                // Rollback single variable
                $this->updateVariable($history['variable_id'], $history['old_value'], $userId);
            } elseif ($history['change_type'] === 'preset_change' && $history['preset_id']) {
                // Rollback to previous preset
                $this->applyPreset($history['preset_id'], $userId);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to rollback: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear theme cache
     */
    private function clearCache(): void
    {
        $this->cache = [];
        
        // Also clear any file-based cache
        $cacheFile = __DIR__ . '/../../cache/theme.css';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    /**
     * Get theme variable by name
     */
    public function getVariableByName(string $name): ?array
    {
        $sql = "SELECT * FROM theme_variables WHERE name = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$name]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Search variables
     */
    public function searchVariables(string $query): array
    {
        $sql = "
            SELECT * FROM theme_variables 
            WHERE name LIKE ? 
            OR display_name LIKE ? 
            OR description LIKE ?
            ORDER BY display_name
        ";
        
        $searchTerm = '%' . $query . '%';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}