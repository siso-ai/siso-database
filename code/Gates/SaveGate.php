<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\Gate;
use SISODatabase\Stream;
use SISODatabase\Database;
use SISODatabase\StorageEngine;

/**
 * SaveGate - Handles SAVE DATABASE commands.
 * 
 * Syntax: SAVE DATABASE 'filename'
 * Example: SAVE DATABASE 'mydb'
 */
class SaveGate extends Gate {
    /**
     * Database instance
     */
    private Database $database;
    
    /**
     * Storage engine
     */
    private StorageEngine $storage;
    
    /**
     * Create save gate
     * 
     * @param Database $database Database to save
     */
    public function __construct(Database $database) {
        $this->database = $database;
        $this->storage = new StorageEngine();
    }
    
    /**
     * Match SAVE DATABASE commands
     * 
     * @param Event $event
     * @return bool
     */
    public function matches(Event $event): bool {
        return preg_match('/^SAVE\s+DATABASE/i', $event->data);
    }
    
    /**
     * Save database to file
     * 
     * @param Event $event
     * @param Stream $stream
     */
    public function transform(Event $event, Stream $stream): void {
        $sql = trim($event->data);
        
        // Parse: SAVE DATABASE 'filename'
        if (!preg_match('/^SAVE\s+DATABASE\s+["\'](.+?)["\']/i', $sql, $matches)) {
            $stream->emit(new Event(
                "RESULT:ERROR: Invalid SAVE DATABASE syntax\n" .
                "Expected: SAVE DATABASE 'filename'",
                $stream->getId()
            ));
            return;
        }
        
        $filename = $matches[1];
        
        try {
            $this->storage->save($this->database, $filename);
            
            $fileSize = $this->storage->getFileSize($filename);
            $fileSizeKB = number_format($fileSize / 1024, 2);
            
            $stream->emit(new Event(
                "RESULT:Database saved to '{$filename}.sisodb' ({$fileSizeKB} KB)",
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
