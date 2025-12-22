<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\DeleteEvent;
use SISODatabase\Gate;
use SISODatabase\Stream;
use SISODatabase\Database;

/**
 * DeleteExecuteGate - Executes DELETE operations.
 * 
 * Deletes rows from the target table based on WHERE clause.
 */
class DeleteExecuteGate extends Gate {
    /**
     * Database instance
     */
    private Database $database;
    
    /**
     * Create execute gate
     * 
     * @param Database $database Database to operate on
     */
    public function __construct(Database $database) {
        $this->database = $database;
    }
    
    /**
     * Match DeleteEvent
     * 
     * @param Event $event
     * @return bool
     */
    public function matches(Event $event): bool {
        return $event instanceof DeleteEvent;
    }
    
    /**
     * Execute DELETE operation
     * 
     * @param Event $event
     * @param Stream $stream
     */
    public function transform(Event $event, Stream $stream): void {
        /** @var DeleteEvent $event */
        
        $table = $this->database->getTable($event->tableName);
        
        if (!$table) {
            $stream->emit(new Event(
                "RESULT:ERROR: Table '{$event->tableName}' does not exist",
                $stream->getId()
            ));
            return;
        }
        
        // Validate WHERE clause columns (if present)
        if ($event->whereClause) {
            $valid = $this->validateWhereColumns($event->whereClause, $table->schema, $event->tableName);
            if (!$valid) {
                $stream->emit(new Event(
                    "RESULT:ERROR: Invalid WHERE clause columns",
                    $stream->getId()
                ));
                return;
            }
        }
        
        // Delete rows
        $predicate = $event->whereClause 
            ? fn($row) => $event->whereClause->evaluate($row)
            : null;
        
        $rowsDeleted = $table->deleteRows($predicate);
        
        $stream->emit(new Event(
            "RESULT:{$rowsDeleted} row" . ($rowsDeleted !== 1 ? 's' : '') . " deleted",
            $stream->getId()
        ));
    }
    
    /**
     * Validate WHERE clause columns exist
     * 
     * @param \SISODatabase\WhereClause $clause
     * @param \SISODatabase\TableSchema $schema
     * @param string $tableName
     * @return bool True if valid, false otherwise
     */
    private function validateWhereColumns($clause, $schema, $tableName): bool {
        if ($clause->isSimple) {
            return $schema->hasColumn($clause->column);
        } else {
            // Compound clause - validate both sides
            return $this->validateWhereColumns($clause->left, $schema, $tableName) &&
                   $this->validateWhereColumns($clause->right, $schema, $tableName);
        }
    }
}
