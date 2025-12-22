<?php

namespace SISODatabase;

/**
 * StorageEngine - Handles database persistence.
 * 
 * Phase 1: JSON-based storage (simple, human-readable)
 * Future phases: Binary pages, WAL, crash recovery
 */
class StorageEngine {
    /**
     * Storage format version
     */
    private const FORMAT_VERSION = 1;
    
    /**
     * Default file extension
     */
    private const FILE_EXTENSION = '.sisodb';
    
    /**
     * Save database to file
     * 
     * @param Database $database The database to save
     * @param string $filename Target filename
     * @throws \RuntimeException If save fails
     */
    public function save(Database $database, string $filename): void {
        // Ensure .sisodb extension
        if (!str_ends_with($filename, self::FILE_EXTENSION)) {
            $filename .= self::FILE_EXTENSION;
        }
        
        // Serialize database to array
        $data = $this->serializeDatabase($database);
        
        // Add metadata
        $fileData = [
            'version' => self::FORMAT_VERSION,
            'created' => date('c'),
            'database' => $data
        ];
        
        // Convert to JSON
        $json = json_encode($fileData, JSON_PRETTY_PRINT);
        
        if ($json === false) {
            throw new \RuntimeException("ERROR: Failed to serialize database: " . json_last_error_msg());
        }
        
        // Write to file
        $result = file_put_contents($filename, $json);
        
        if ($result === false) {
            throw new \RuntimeException("ERROR: Failed to write to file: {$filename}");
        }
    }
    
    /**
     * Load database from file
     * 
     * @param string $filename Source filename
     * @return Database The loaded database
     * @throws \RuntimeException If load fails
     */
    public function load(string $filename): Database {
        // Ensure .sisodb extension
        if (!str_ends_with($filename, self::FILE_EXTENSION)) {
            $filename .= self::FILE_EXTENSION;
        }
        
        // Check file exists
        if (!file_exists($filename)) {
            throw new \RuntimeException("ERROR: Database file not found: {$filename}");
        }
        
        // Read file
        $json = file_get_contents($filename);
        
        if ($json === false) {
            throw new \RuntimeException("ERROR: Failed to read file: {$filename}");
        }
        
        // Parse JSON
        $fileData = json_decode($json, true);
        
        if ($fileData === null) {
            throw new \RuntimeException("ERROR: Failed to parse database file: " . json_last_error_msg());
        }
        
        // Validate format version
        if (!isset($fileData['version']) || $fileData['version'] !== self::FORMAT_VERSION) {
            throw new \RuntimeException("ERROR: Incompatible database format version");
        }
        
        // Deserialize database
        return $this->deserializeDatabase($fileData['database']);
    }
    
    /**
     * Check if database file exists
     * 
     * @param string $filename Filename to check
     * @return bool
     */
    public function exists(string $filename): bool {
        if (!str_ends_with($filename, self::FILE_EXTENSION)) {
            $filename .= self::FILE_EXTENSION;
        }
        
        return file_exists($filename);
    }
    
    /**
     * Delete database file
     * 
     * @param string $filename File to delete
     * @return bool Success
     */
    public function delete(string $filename): bool {
        if (!str_ends_with($filename, self::FILE_EXTENSION)) {
            $filename .= self::FILE_EXTENSION;
        }
        
        if (file_exists($filename)) {
            return unlink($filename);
        }
        
        return false;
    }
    
    /**
     * Get database file size
     * 
     * @param string $filename Filename
     * @return int File size in bytes
     */
    public function getFileSize(string $filename): int {
        if (!str_ends_with($filename, self::FILE_EXTENSION)) {
            $filename .= self::FILE_EXTENSION;
        }
        
        if (file_exists($filename)) {
            return filesize($filename);
        }
        
        return 0;
    }
    
    /**
     * Serialize database to array
     * 
     * @param Database $database
     * @return array
     */
    private function serializeDatabase(Database $database): array {
        $tables = [];
        
        foreach ($database->getTableNames() as $tableName) {
            $table = $database->getTable($tableName);
            
            $tables[$tableName] = [
                'schema' => $this->serializeSchema($table->schema),
                'rows' => $this->serializeRows($table->getAllRows())
            ];
        }
        
        return ['tables' => $tables];
    }
    
    /**
     * Serialize table schema
     * 
     * @param TableSchema $schema
     * @return array
     */
    private function serializeSchema(TableSchema $schema): array {
        $columns = [];
        
        foreach ($schema->getColumns() as $colName => $colDef) {
            if ($colDef instanceof ColumnDefinition) {
                $columns[$colName] = [
                    'type' => $colDef->type,
                    'primaryKey' => $colDef->primaryKey,
                    'notNull' => $colDef->notNull,
                    'default' => $colDef->defaultValue
                ];
            } else {
                // Fallback for older schemas
                $columns[$colName] = ['type' => $colDef];
            }
        }
        
        return [
            'name' => $schema->name,
            'columns' => $columns
        ];
    }
    
    /**
     * Serialize rows
     * 
     * @param array $rows
     * @return array
     */
    private function serializeRows(array $rows): array {
        $serialized = [];
        
        foreach ($rows as $row) {
            $serialized[] = $row->values;
        }
        
        return $serialized;
    }
    
    /**
     * Deserialize database from array
     * 
     * @param array $data
     * @return Database
     */
    private function deserializeDatabase(array $data): Database {
        $database = new Database();
        
        foreach ($data['tables'] as $tableName => $tableData) {
            // Deserialize schema
            $schema = $this->deserializeSchema($tableData['schema']);
            
            // Create table
            $database->createTable($schema);
            
            // Deserialize rows
            $rows = $this->deserializeRows($tableData['rows']);
            
            // Insert rows
            $table = $database->getTable($tableName);
            foreach ($rows as $row) {
                $table->insert($row);
            }
        }
        
        return $database;
    }
    
    /**
     * Deserialize table schema
     * 
     * @param array $data
     * @return TableSchema
     */
    private function deserializeSchema(array $data): TableSchema {
        $schema = new TableSchema($data['name']);
        
        foreach ($data['columns'] as $colName => $colData) {
            $colDef = new ColumnDefinition(
                $colName,
                $colData['type'],
                $colData['primaryKey'] ?? false,
                $colData['notNull'] ?? false,
                $colData['default'] ?? null
            );
            
            $schema->addColumnDefinition($colDef);
        }
        
        return $schema;
    }
    
    /**
     * Deserialize rows
     * 
     * @param array $data
     * @return array
     */
    private function deserializeRows(array $data): array {
        $rows = [];
        
        foreach ($data as $rowData) {
            $rows[] = new Row($rowData);
        }
        
        return $rows;
    }
}
