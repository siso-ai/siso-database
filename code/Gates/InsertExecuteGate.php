<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\Gate;
use SISODatabase\Stream;
use SISODatabase\InsertEvent;
use SISODatabase\Database;
use SISODatabase\Row;

/**
 * InsertExecuteGate - Executes INSERT operations.
 * 
 * Takes InsertEvent and adds rows to the appropriate table.
 * Validates column counts and names.
 */
class InsertExecuteGate extends Gate {
    private Database $database;
    
    public function __construct(Database $database) {
        $this->database = $database;
    }
    
    public function matches(Event $event): bool {
        return $event instanceof InsertEvent;
    }
    
    public function transform(Event $event, Stream $stream): void {
        /** @var InsertEvent $event */
        
        $table = $this->database->getTable($event->tableName);
        
        if (!$table) {
            $stream->emit(new Event(
                "RESULT:ERROR: Table '{$event->tableName}' does not exist",
                $stream->getId()
            ));
            return;
        }
        
        $schema = $table->schema;
        
        // Phase 3: Batch insert
        if ($event->isBatch) {
            $rowsInserted = 0;
            
            foreach ($event->values as $rowValues) {
                if ($event->hasColumnNames) {
                    // Batch with column names
                    $row = $this->createRowWithColumns($schema, $event->columns, $rowValues, $stream, $event->tableName);
                } else {
                    // Batch without column names
                    $row = $this->createRowAllColumns($schema, $rowValues, $stream, $event->tableName);
                }
                
                if ($row === null) {
                    // Error was already emitted
                    return;
                }
                
                $table->insert($row);
                $rowsInserted++;
            }
            
            $stream->emit(new Event(
                "RESULT:{$rowsInserted} row" . ($rowsInserted !== 1 ? 's' : '') . " inserted into '{$event->tableName}'",
                $stream->getId()
            ));
            
        } elseif ($event->hasColumnNames) {
            // Phase 2: With column names
            $row = $this->createRowWithColumns($schema, $event->columns, $event->values, $stream, $event->tableName);
            
            if ($row === null) {
                return;
            }
            
            $table->insert($row);
            
            $stream->emit(new Event(
                "RESULT:1 row inserted into '{$event->tableName}'",
                $stream->getId()
            ));
            
        } else {
            // Phase 1: All values in order
            $row = $this->createRowAllColumns($schema, $event->values, $stream, $event->tableName);
            
            if ($row === null) {
                return;
            }
            
            $table->insert($row);
            
            $stream->emit(new Event(
                "RESULT:1 row inserted into '{$event->tableName}'",
                $stream->getId()
            ));
        }
    }
    
    /**
     * Create row with column names specified (Phase 2)
     */
    private function createRowWithColumns($schema, $columns, $values, $stream, $tableName): ?Row {
        // Validate all specified columns exist
        foreach ($columns as $col) {
            if (!$schema->hasColumn($col)) {
                $stream->emit(new Event(
                    "RESULT:ERROR: Column '{$col}' does not exist in table '{$tableName}'",
                    $stream->getId()
                ));
                return null;
            }
        }
        
        // Build row with specified values, NULL for unspecified
        $rowData = [];
        
        foreach ($schema->getColumnNames() as $col) {
            if (isset($values[$col])) {
                $rowData[$col] = $values[$col];
            } else {
                // Check if column has default value
                $columnDef = $schema->getColumn($col);
                if ($columnDef && $columnDef->hasDefault()) {
                    $rowData[$col] = $columnDef->defaultValue;
                } else {
                    $rowData[$col] = null;
                }
            }
        }
        
        return new Row($rowData);
    }
    
    /**
     * Create row with all columns in order (Phase 1)
     */
    private function createRowAllColumns($schema, $values, $stream, $tableName): ?Row {
        $expectedCount = $schema->getColumnCount();
        $actualCount = count($values);
        
        if ($actualCount !== $expectedCount) {
            $stream->emit(new Event(
                "RESULT:ERROR: Column count mismatch. Table '{$tableName}' has {$expectedCount} columns, " .
                "but INSERT provides {$actualCount} values",
                $stream->getId()
            ));
            return null;
        }
        
        // Map values to columns by position
        $rowData = [];
        $columnNames = $schema->getColumnNames();
        
        foreach ($values as $i => $value) {
            $columnName = $columnNames[$i];
            $rowData[$columnName] = $value;
        }
        
        return new Row($rowData);
    }
}
