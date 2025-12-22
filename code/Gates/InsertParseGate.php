<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\Gate;
use SISODatabase\Stream;
use SISODatabase\InsertEvent;

/**
 * InsertParseGate - Parses INSERT SQL statements.
 * 
 * Supports:
 * - Phase 1: INSERT INTO table VALUES (val1, val2, ...)
 * - Phase 2: INSERT INTO table (col1, col2) VALUES (val1, val2)
 * - Phase 3: INSERT INTO table VALUES (row1), (row2), (row3)
 */
class InsertParseGate extends Gate {
    public function matches(Event $event): bool {
        // Don't match if it's already an InsertEvent
        if ($event instanceof InsertEvent) {
            return false;
        }
        return preg_match('/^INSERT\s+INTO\s+/i', $event->data);
    }
    
    public function transform(Event $event, Stream $stream): void {
        $sql = trim($event->data);
        
        // Phase 2: With column names
        // INSERT INTO table (col1, col2) VALUES (val1, val2)
        if (preg_match('/^INSERT\s+INTO\s+(\w+)\s*\(([\w\s,]+)\)\s+VALUES\s+(.+)$/i', $sql, $matches)) {
            $tableName = $matches[1];
            $columnList = $matches[2];
            $valuesSection = $matches[3];
            
            // Parse column names
            $columns = array_map('trim', explode(',', $columnList));
            
            // Check for batch insert (Phase 3 with column names)
            if ($this->isBatchValues($valuesSection)) {
                $this->parseBatchInsert($stream, $event->data, $tableName, $valuesSection, true, $columns);
            } else {
                // Single row insert with column names
                $values = $this->parseValuesList($valuesSection);
                
                if ($values === null) {
                    $stream->emit(new Event(
                        "RESULT:ERROR: Invalid VALUES syntax",
                        $stream->getId()
                    ));
                    return;
                }
                
                // Check column count matches value count
                if (count($columns) !== count($values)) {
                    $stream->emit(new Event(
                        "RESULT:ERROR: Column count (" . count($columns) . ") does not match value count (" . count($values) . ")",
                        $stream->getId()
                    ));
                    return;
                }
                
                // Map columns to values
                $mappedValues = [];
                foreach ($columns as $i => $col) {
                    $mappedValues[$col] = $values[$i];
                }
                
                $stream->emit(new InsertEvent(
                    $event->data,
                    $stream->getId(),
                    $tableName,
                    $mappedValues,
                    true,
                    $columns,
                    false
                ));
            }
            
        // Phase 1/3: Without column names
        // INSERT INTO table VALUES (val1, val2)
        } elseif (preg_match('/^INSERT\s+INTO\s+(\w+)\s+VALUES\s+(.+)$/i', $sql, $matches)) {
            $tableName = $matches[1];
            $valuesSection = $matches[2];
            
            // Check for batch insert (Phase 3)
            if ($this->isBatchValues($valuesSection)) {
                $this->parseBatchInsert($stream, $event->data, $tableName, $valuesSection, false, null);
            } else {
                // Single row insert without column names
                $values = $this->parseValuesList($valuesSection);
                
                if ($values === null) {
                    $stream->emit(new Event(
                        "RESULT:ERROR: Invalid VALUES syntax",
                        $stream->getId()
                    ));
                    return;
                }
                
                $stream->emit(new InsertEvent(
                    $event->data,
                    $stream->getId(),
                    $tableName,
                    $values,
                    false,
                    null,
                    false
                ));
            }
            
        } else {
            $stream->emit(new Event(
                "RESULT:ERROR: Invalid INSERT syntax: {$event->data}\n" .
                "Expected: INSERT INTO tablename VALUES (...)\n" .
                "      or: INSERT INTO tablename (columns) VALUES (...)",
                $stream->getId()
            ));
        }
    }
    
    /**
     * Check if values section contains multiple rows (batch insert)
     */
    private function isBatchValues(string $valuesSection): bool {
        // Look for pattern: (...), (...)
        return preg_match('/\)\s*,\s*\(/', $valuesSection) > 0;
    }
    
    /**
     * Parse batch insert
     */
    private function parseBatchInsert(
        Stream $stream,
        string $originalSql,
        string $tableName,
        string $valuesSection,
        bool $hasColumnNames,
        ?array $columns
    ): void {
        // Split on "), (" pattern
        $valuesSets = preg_split('/\)\s*,\s*\(/', $valuesSection);
        
        if (empty($valuesSets)) {
            $stream->emit(new Event(
                "RESULT:ERROR: Invalid batch VALUES syntax",
                $stream->getId()
            ));
            return;
        }
        
        $allRows = [];
        
        foreach ($valuesSets as $i => $valuesSet) {
            // Clean up - remove leading ( and trailing )
            $valuesSet = trim($valuesSet);
            $valuesSet = ltrim($valuesSet, '(');
            $valuesSet = rtrim($valuesSet, ')');
            
            $values = $this->parseValues($valuesSet);
            
            if ($values === null) {
                $stream->emit(new Event(
                    "RESULT:ERROR: Invalid values in row " . ($i + 1),
                    $stream->getId()
                ));
                return;
            }
            
            if ($hasColumnNames) {
                // Map to columns
                if (count($columns) !== count($values)) {
                    $stream->emit(new Event(
                        "RESULT:ERROR: Row " . ($i + 1) . " has wrong number of values",
                        $stream->getId()
                    ));
                    return;
                }
                
                $mappedValues = [];
                foreach ($columns as $j => $col) {
                    $mappedValues[$col] = $values[$j];
                }
                $allRows[] = $mappedValues;
            } else {
                $allRows[] = $values;
            }
        }
        
        // Emit batch insert event
        $stream->emit(new InsertEvent(
            $originalSql,
            $stream->getId(),
            $tableName,
            $allRows,
            $hasColumnNames,
            $columns,
            true
        ));
    }
    
    /**
     * Parse a VALUES list wrapped in parentheses
     * E.g., "(1, 'hello', NULL)" => [1, 'hello', null]
     */
    private function parseValuesList(string $valuesSection): ?array {
        // Remove parentheses
        $valuesSection = trim($valuesSection);
        if (!preg_match('/^\s*\((.*)\)\s*$/', $valuesSection, $matches)) {
            return null;
        }
        
        return $this->parseValues($matches[1]);
    }
    
    /**
     * Parse comma-separated values
     * E.g., "1, 'hello', NULL" => [1, 'hello', null]
     */
    private function parseValues(string $valuesStr): ?array {
        $values = [];
        $current = '';
        $inString = false;
        $stringChar = null;
        
        for ($i = 0; $i < strlen($valuesStr); $i++) {
            $char = $valuesStr[$i];
            
            if ($inString) {
                if ($char === $stringChar) {
                    // Check for escaped quote
                    if ($i + 1 < strlen($valuesStr) && $valuesStr[$i + 1] === $stringChar) {
                        $current .= $stringChar;
                        $i++; // Skip next char
                    } else {
                        $inString = false;
                        $stringChar = null;
                    }
                } else {
                    $current .= $char;
                }
            } else {
                if ($char === '"' || $char === "'") {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === ',') {
                    $values[] = $this->parseValue(trim($current));
                    $current = '';
                } else {
                    $current .= $char;
                }
            }
        }
        
        // Add last value
        if ($current !== '' || !empty($values)) {
            $values[] = $this->parseValue(trim($current));
        }
        
        return $values;
    }
    
    /**
     * Parse a single value
     */
    private function parseValue(string $value): mixed {
        // NULL
        if (strtoupper($value) === 'NULL') {
            return null;
        }
        
        // Integer
        if (preg_match('/^-?\d+$/', $value)) {
            return (int)$value;
        }
        
        // Float
        if (preg_match('/^-?\d+\.\d+$/', $value)) {
            return (float)$value;
        }
        
        // String (already unquoted by parseValues)
        return $value;
    }
}
