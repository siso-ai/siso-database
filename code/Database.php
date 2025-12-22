<?php

namespace SISODatabase;

/**
 * Database - In-memory database storage.
 * 
 * Stores table schemas and data. This is the main database object
 * that gates interact with to store and retrieve information.
 */
class Database {
    /**
     * Tables indexed by name
     * @var array<string, Table>
     */
    private array $tables = [];
    
    /**
     * Create a table in the database
     * 
     * @param TableSchema $schema The table schema to create
     * @throws \RuntimeException If table already exists
     */
    public function createTable(TableSchema $schema): void {
        if ($this->hasTable($schema->name)) {
            throw new \RuntimeException("ERROR: Table '{$schema->name}' already exists");
        }
        
        // Create Table object with schema
        $this->tables[$schema->name] = new Table($schema);
    }
    
    /**
     * Drop a table from the database
     * 
     * @param string $name Table name
     * @throws \RuntimeException If table doesn't exist
     */
    public function dropTable(string $name): void {
        if (!$this->hasTable($name)) {
            throw new \RuntimeException("ERROR: Table '{$name}' does not exist");
        }
        
        unset($this->tables[$name]);
    }
    
    /**
     * Check if table exists
     * 
     * @param string $name Table name
     * @return bool
     */
    public function hasTable(string $name): bool {
        return isset($this->tables[$name]);
    }
    
    /**
     * Get a table
     * 
     * @param string $name Table name
     * @return Table|null
     */
    public function getTable(string $name): ?Table {
        return $this->tables[$name] ?? null;
    }
    
    /**
     * Get a table schema
     * 
     * @param string $name Table name
     * @return TableSchema|null
     */
    public function getTableSchema(string $name): ?TableSchema {
        $table = $this->getTable($name);
        return $table ? $table->schema : null;
    }
    
    /**
     * Get all table names
     * 
     * @return array
     */
    public function getTableNames(): array {
        return array_keys($this->tables);
    }
    
    /**
     * Get number of tables
     * 
     * @return int
     */
    public function getTableCount(): int {
        return count($this->tables);
    }
    
    /**
     * Get detailed database info
     * 
     * @return array
     */
    public function getInfo(): array {
        $info = [
            'tables' => [],
            'total_tables' => $this->getTableCount()
        ];
        
        foreach ($this->tables as $name => $table) {
            $schema = $table->schema;
            $info['tables'][$name] = [
                'columns' => $schema->getColumnCount(),
                'column_names' => $schema->getColumnNames(),
                'primary_key' => $schema->getPrimaryKey(),
                'row_count' => $table->count()
            ];
        }
        
        return $info;
    }
    
    /**
     * Clear all tables (for testing)
     */
    public function clear(): void {
        $this->tables = [];
    }
}
