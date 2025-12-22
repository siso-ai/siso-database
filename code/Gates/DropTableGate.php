<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\Gate;
use SISODatabase\Stream;
use SISODatabase\Database;

/**
 * DropTableGate - Handles DROP TABLE statements.
 * 
 * Supports:
 * - DROP TABLE tablename
 * - DROP TABLE IF EXISTS tablename
 */
class DropTableGate extends Gate {
    private Database $database;
    
    public function __construct(Database $database) {
        $this->database = $database;
    }
    
    public function matches(Event $event): bool {
        return preg_match('/^DROP\s+TABLE\s+/i', $event->data);
    }
    
    public function transform(Event $event, Stream $stream): void {
        // Match: DROP TABLE tablename
        // or: DROP TABLE IF EXISTS tablename
        
        $ifExists = false;
        
        if (preg_match('/^DROP\s+TABLE\s+IF\s+EXISTS\s+(\w+)/i', $event->data, $matches)) {
            $ifExists = true;
            $tableName = $matches[1];
        } elseif (preg_match('/^DROP\s+TABLE\s+(\w+)/i', $event->data, $matches)) {
            $tableName = $matches[1];
        } else {
            $stream->emit(new Event(
                "RESULT:ERROR: Invalid DROP TABLE syntax",
                $stream->getId()
            ));
            return;
        }
        
        if (!$this->database->hasTable($tableName)) {
            if ($ifExists) {
                // IF EXISTS - not an error
                $stream->emit(new Event(
                    "RESULT:Table '{$tableName}' does not exist (skipped)",
                    $stream->getId()
                ));
            } else {
                $stream->emit(new Event(
                    "RESULT:ERROR: Table '{$tableName}' does not exist",
                    $stream->getId()
                ));
            }
            return;
        }
        
        try {
            $this->database->dropTable($tableName);
            $stream->emit(new Event(
                "RESULT:Table '{$tableName}' dropped",
                $stream->getId()
            ));
        } catch (\RuntimeException $e) {
            $stream->emit(new Event(
                $e->getMessage(),
                $stream->getId()
            ));
        }
    }
}
