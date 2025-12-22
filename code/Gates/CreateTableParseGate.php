<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\Gate;
use SISODatabase\Stream;
use SISODatabase\TableSchema;
use SISODatabase\ColumnDefinition;
use SISODatabase\SchemaEvent;

/**
 * CreateTableParseGate - Parses CREATE TABLE SQL statements.
 * 
 * Supports:
 * - CREATE TABLE tablename (columns)
 * - CREATE TABLE IF NOT EXISTS
 * - Column types: INTEGER, TEXT, REAL, BLOB
 * - Constraints: PRIMARY KEY, NOT NULL, DEFAULT
 */
class CreateTableParseGate extends Gate {
    private const VALID_TYPES = ['INTEGER', 'TEXT', 'REAL', 'BLOB'];
    
    public function matches(Event $event): bool {
        return preg_match('/^CREATE\s+TABLE\s+/i', $event->data);
    }
    
    public function transform(Event $event, Stream $stream): void {
        $sql = $event->data;
        
        // Check for IF NOT EXISTS
        $ifNotExists = false;
        if (preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+/i', $sql)) {
            $ifNotExists = true;
        }
        
        // Match: CREATE TABLE [IF NOT EXISTS] tablename (columns)
        if ($ifNotExists) {
            $pattern = '/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+(\w+)\s*\((.*)\)/i';
        } else {
            $pattern = '/^CREATE\s+TABLE\s+(\w+)\s*\((.*)\)/i';
        }
        
        if (!preg_match($pattern, $sql, $matches)) {
            $stream->emit(new Event(
                "RESULT:ERROR: Invalid CREATE TABLE syntax",
                $stream->getId()
            ));
            return;
        }
        
        $tableName = $matches[1];
        $columnDefs = $matches[2];
        
        // Create schema
        $schema = new TableSchema($tableName);
        
        // Parse columns
        $columns = array_map('trim', explode(',', $columnDefs));
        
        foreach ($columns as $columnDef) {
            if (empty($columnDef)) {
                continue;
            }
            
            // Parse column definition
            // Format: column_name [TYPE] [PRIMARY KEY] [NOT NULL] [DEFAULT value]
            $tokens = preg_split('/\s+/', trim($columnDef));
            $columnName = array_shift($tokens);
            
            if (empty($columnName)) {
                $stream->emit(new Event(
                    "RESULT:ERROR: Empty column name",
                    $stream->getId()
                ));
                return;
            }
            
            // Create column with default type
            $column = new ColumnDefinition($columnName, 'TEXT');
            $typeSet = false;
            
            // Parse remaining tokens
            $i = 0;
            while ($i < count($tokens)) {
                $token = strtoupper($tokens[$i]);
                
                // Check for type
                if (in_array($token, self::VALID_TYPES)) {
                    if ($typeSet) {
                        $stream->emit(new Event(
                            "RESULT:ERROR: Multiple types specified for column '{$columnName}'",
                            $stream->getId()
                        ));
                        return;
                    }
                    $column = new ColumnDefinition($columnName, $token);
                    $typeSet = true;
                    $i++;
                }
                // Check for PRIMARY KEY
                elseif ($token === 'PRIMARY') {
                    // Expect 'KEY' next
                    if ($i + 1 >= count($tokens) || strtoupper($tokens[$i + 1]) !== 'KEY') {
                        $stream->emit(new Event(
                            "RESULT:ERROR: Expected KEY after PRIMARY in column '{$columnName}'",
                            $stream->getId()
                        ));
                        return;
                    }
                    
                    // Check for multiple primary keys
                    if ($schema->hasPrimaryKey()) {
                        $stream->emit(new Event(
                            "RESULT:ERROR: Table can have only one PRIMARY KEY",
                            $stream->getId()
                        ));
                        return;
                    }
                    
                    $column->setPrimaryKey();
                    $i += 2; // Skip 'PRIMARY' and 'KEY'
                }
                // Check for NOT NULL
                elseif ($token === 'NOT') {
                    // Expect 'NULL' next
                    if ($i + 1 >= count($tokens) || strtoupper($tokens[$i + 1]) !== 'NULL') {
                        $stream->emit(new Event(
                            "RESULT:ERROR: Expected NULL after NOT in column '{$columnName}'",
                            $stream->getId()
                        ));
                        return;
                    }
                    
                    $column->setNotNull();
                    $i += 2; // Skip 'NOT' and 'NULL'
                }
                // Check for DEFAULT
                elseif ($token === 'DEFAULT') {
                    if ($i + 1 >= count($tokens)) {
                        $stream->emit(new Event(
                            "RESULT:ERROR: Expected value after DEFAULT in column '{$columnName}'",
                            $stream->getId()
                        ));
                        return;
                    }
                    
                    $defaultValue = $tokens[$i + 1];
                    
                    // Remove quotes if present
                    if (preg_match('/^["\'](.*)["\']\$/', $defaultValue, $m)) {
                        $defaultValue = $m[1];
                    }
                    
                    $column->setDefault($defaultValue);
                    $i += 2; // Skip 'DEFAULT' and value
                }
                else {
                    $stream->emit(new Event(
                        "RESULT:ERROR: Unknown keyword '{$token}' in column '{$columnName}'",
                        $stream->getId()
                    ));
                    return;
                }
            }
            
            $schema->addColumnDefinition($column);
        }
        
        // Check for at least one column
        if ($schema->getColumnCount() === 0) {
            $stream->emit(new Event(
                "RESULT:ERROR: Table must have at least one column",
                $stream->getId()
            ));
            return;
        }
        
        // Emit schema event with IF NOT EXISTS flag in data
        $data = $ifNotExists ? "IF_NOT_EXISTS:{$tableName}" : $tableName;
        $stream->emit(new SchemaEvent($data, $stream->getId(), $schema));
    }
}
