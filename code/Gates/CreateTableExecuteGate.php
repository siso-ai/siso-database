<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\Gate;
use SISODatabase\Stream;
use SISODatabase\SchemaEvent;
use SISODatabase\Database;

/**
 * CreateTableExecuteGate - Executes table creation in the database.
 * 
 * Takes SchemaEvent from CreateTableParseGate and stores the schema
 * in the Database object.
 */
class CreateTableExecuteGate extends Gate {
    private Database $database;
    
    public function __construct(Database $database) {
        $this->database = $database;
    }
    
    public function matches(Event $event): bool {
        return $event instanceof SchemaEvent;
    }
    
    public function transform(Event $event, Stream $stream): void {
        /** @var SchemaEvent $event */
        $schema = $event->schema;
        $tableName = $schema->name;
        
        // Check for IF NOT EXISTS flag
        $ifNotExists = str_starts_with($event->data, 'IF_NOT_EXISTS:');
        
        // Check if table already exists
        if ($this->database->hasTable($tableName)) {
            if ($ifNotExists) {
                // IF NOT EXISTS - not an error, just skip
                $stream->emit(new Event(
                    "RESULT:Table '{$tableName}' already exists (skipped)",
                    $stream->getId()
                ));
                return;
            } else {
                // Error - table exists
                $stream->emit(new Event(
                    "RESULT:ERROR: Table '{$tableName}' already exists",
                    $stream->getId()
                ));
                return;
            }
        }
        
        // Create the table
        try {
            $this->database->createTable($schema);
            $stream->emit(new Event(
                "RESULT:Table '{$tableName}' created",
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
