<?php

namespace SISODatabase;

/**
 * TableSchema - Represents the structure of a database table.
 * 
 * Contains table name and column definitions. This is the in-memory
 * representation of table metadata.
 */
class TableSchema {
    /**
     * Table name
     */
    public readonly string $name;
    
    /**
     * Column definitions
     * Array of column name => ColumnDefinition
     */
    private array $columns = [];
    
    /**
     * Primary key column name (if any)
     */
    private ?string $primaryKey = null;
    
    /**
     * Create a new table schema
     * 
     * @param string $name Table name
     */
    public function __construct(string $name) {
        $this->name = $name;
    }
    
    /**
     * Add a column definition to the schema
     * 
     * @param ColumnDefinition $column Column definition
     */
    public function addColumnDefinition(ColumnDefinition $column): void {
        $this->columns[$column->name] = $column;
        
        // Track primary key
        if ($column->primaryKey) {
            $this->primaryKey = $column->name;
        }
    }
    
    /**
     * Add a simple column (Phase 1 compatibility)
     * 
     * @param string $name Column name
     * @param string $type Column type (default: TEXT)
     */
    public function addColumn(string $name, string $type = 'TEXT'): void {
        $this->columns[$name] = new ColumnDefinition($name, $type);
    }
    
    /**
     * Check if column exists
     * 
     * @param string $name Column name
     * @return bool
     */
    public function hasColumn(string $name): bool {
        return isset($this->columns[$name]);
    }
    
    /**
     * Get column definition
     * 
     * @param string $name Column name
     * @return ColumnDefinition|null
     */
    public function getColumn(string $name): ?ColumnDefinition {
        return $this->columns[$name] ?? null;
    }
    
    /**
     * Get column type (Phase 1 compatibility)
     * 
     * @param string $name Column name
     * @return string|null Column type or null if not found
     */
    public function getColumnType(string $name): ?string {
        $column = $this->getColumn($name);
        return $column ? $column->type : null;
    }
    
    /**
     * Get all column names
     * 
     * @return array
     */
    public function getColumnNames(): array {
        return array_keys($this->columns);
    }
    
    /**
     * Get all column definitions
     * 
     * @return array<string, ColumnDefinition>
     */
    public function getColumns(): array {
        return $this->columns;
    }
    
    /**
     * Get number of columns
     * 
     * @return int
     */
    public function getColumnCount(): int {
        return count($this->columns);
    }
    
    /**
     * Check if table has a primary key
     * 
     * @return bool
     */
    public function hasPrimaryKey(): bool {
        return $this->primaryKey !== null;
    }
    
    /**
     * Get primary key column name
     * 
     * @return string|null
     */
    public function getPrimaryKey(): ?string {
        return $this->primaryKey;
    }
    
    /**
     * String representation for debugging
     * 
     * @return string
     */
    public function __toString(): string {
        $cols = [];
        foreach ($this->columns as $name => $column) {
            if ($column instanceof ColumnDefinition) {
                $def = "{$column->name} {$column->type}";
                if ($column->primaryKey) $def .= " PRIMARY KEY";
                if ($column->notNull) $def .= " NOT NULL";
                if ($column->defaultValue !== null) $def .= " DEFAULT {$column->defaultValue}";
                $cols[] = $def;
            } else {
                // Legacy format (shouldn't happen but for safety)
                $cols[] = "{$name} {$column}";
            }
        }
        return "{$this->name} (" . implode(', ', $cols) . ")";
    }
}
