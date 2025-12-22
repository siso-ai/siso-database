<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\SelectEvent;
use SISODatabase\RowSetEvent;
use SISODatabase\Gate;
use SISODatabase\Stream;
use SISODatabase\Database;

/**
 * TableScanGate - Performs full table scan.
 * 
 * Reads all rows from a table and emits RowSetEvent.
 */
class TableScanGate extends Gate {
    /**
     * Database instance
     */
    private Database $database;
    
    /**
     * Create table scan gate
     * 
     * @param Database $database The database to scan
     */
    public function __construct(Database $database) {
        $this->database = $database;
    }
    
    /**
     * Match SelectEvent instances
     * 
     * @param Event $event
     * @return bool
     */
    public function matches(Event $event): bool {
        return $event instanceof SelectEvent;
    }
    
    /**
     * Scan table and emit RowSetEvent
     * 
     * @param Event $event
     * @param Stream $stream
     */
    public function transform(Event $event, Stream $stream): void {
        /** @var SelectEvent $event */
        
        $table = $this->database->getTable($event->tableName);
        
        if (!$table) {
            $stream->emit(new Event(
                "RESULT:ERROR: Table '{$event->tableName}' does not exist",
                $stream->getId()
            ));
            return;
        }
        
        // Get all rows
        $rows = $table->getAllRows();
        
        // Determine which columns to include
        $columnNames = [];
        if ($event->selectAll) {
            // SELECT * - all columns
            $columnNames = $table->schema->getColumnNames();
        } else {
            // Validate all requested columns exist
            foreach ($event->columns as $col) {
                if (!$table->schema->hasColumn($col)) {
                    $stream->emit(new Event(
                        "RESULT:ERROR: Column '{$col}' does not exist in table '{$event->tableName}'",
                        $stream->getId()
                    ));
                    return;
                }
            }
            $columnNames = $event->columns;
        }
        
        // Emit rowset event
        $stream->emit(new RowSetEvent(
            "scanned",
            $stream->getId(),
            $rows,
            $columnNames,
            $event
        ));
    }
}
