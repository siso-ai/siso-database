<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\Gate;
use SISODatabase\Stream;
use SISODatabase\Database;
use SISODatabase\StorageEngine;

/**
 * LoadGate - Handles LOAD DATABASE commands.
 * 
 * Syntax: LOAD DATABASE 'filename'
 * Example: LOAD DATABASE 'mydb'
 * 
 * Note: Loads database by clearing current database and importing saved data.
 */
class LoadGate extends Gate {
    /**
     * Database instance
     */
    private Database $database;
    
    /**
     * Storage engine
     */
    private StorageEngine $storage;
    
    /**
     * Create load gate
     * 
     * @param Database $database Database to load into
     */
    public function __construct(Database $database) {
        $this->database = $database;
        $this->storage = new StorageEngine();
    }
    
    /**
     * Match LOAD DATABASE commands
     * 
     * @param Event $event
     * @return bool
     */
    public function matches(Event $event): bool {
        return preg_match('/^LOAD\s+DATABASE/i', $event->data);
    }
    
    /**
     * Load database from file
     * 
     * @param Event $event
     * @param Stream $stream
     */
    public function transform(Event $event, Stream $stream): void {
        $sql = trim($event->data);
        
        // Parse: LOAD DATABASE 'filename'
        if (!preg_match('/^LOAD\s+DATABASE\s+["\'](.+?)["\']/i', $sql, $matches)) {
            $stream->emit(new Event(
                "RESULT:ERROR: Invalid LOAD DATABASE syntax\n" .
                "Expected: LOAD DATABASE 'filename'",
                $stream->getId()
            ));
            return;
        }
        
        $filename = $matches[1];
        
        try {
            // Load database from file
            $loadedDb = $this->storage->load($filename);
            
            // Clear current database
            $this->database->clear();
            
            // Copy all tables from loaded database to current database
            foreach ($loadedDb->getTableNames() as $tableName) {
                $table = $loadedDb->getTable($tableName);
                
                // Create table with schema
                $this->database->createTable($table->schema);
                
                // Insert all rows
                $targetTable = $this->database->getTable($tableName);
                foreach ($table->getAllRows() as $row) {
                    $targetTable->insert($row);
                }
            }
            
            $tableCount = $this->database->getTableCount();
            $fileSize = $this->storage->getFileSize($filename);
            $fileSizeKB = number_format($fileSize / 1024, 2);
            
            $stream->emit(new Event(
                "RESULT:Database loaded from '{$filename}.sisodb' ({$tableCount} tables, {$fileSizeKB} KB)",
                $stream->getId()
            ));
        } catch (\RuntimeException $e) {
            $stream->emit(new Event(
                "RESULT:" . $e->getMessage(),
                $stream->getId()
            ));
        }
    }
}
