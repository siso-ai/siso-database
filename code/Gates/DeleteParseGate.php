<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\Gate;
use SISODatabase\Stream;
use SISODatabase\DeleteEvent;
use SISODatabase\WhereClause;

/**
 * DeleteParseGate - Parses DELETE statements.
 * 
 * Phase 3: DELETE FROM table
 * Phase 4: DELETE FROM table WHERE condition
 */
class DeleteParseGate extends Gate {
    /**
     * Match DELETE statements (only base Event, not DeleteEvent)
     * 
     * @param Event $event
     * @return bool
     */
    public function matches(Event $event): bool {
        // Don't match our own output
        if (get_class($event) !== 'SISODatabase\Event') {
            return false;
        }
        
        return preg_match('/^DELETE\s+FROM\s+/i', $event->data);
    }
    
    /**
     * Parse DELETE and emit DeleteEvent
     * 
     * @param Event $event
     * @param Stream $stream
     */
    public function transform(Event $event, Stream $stream): void {
        $sql = trim($event->data);
        
        // Parse: DELETE FROM tablename WHERE condition
        if (!preg_match('/^DELETE\s+FROM\s+(\w+)(?:\s+WHERE\s+(.+))?$/i', $sql, $matches)) {
            $stream->emit(new Event(
                "RESULT:ERROR: Invalid DELETE syntax\n" .
                "Expected: DELETE FROM tablename [WHERE condition]",
                $stream->getId()
            ));
            return;
        }
        
        $tableName = $matches[1];
        $whereStr = isset($matches[2]) ? trim($matches[2]) : null;
        
        // Parse WHERE clause if present
        $whereClause = null;
        if ($whereStr !== null) {
            $whereClause = $this->parseWhere($whereStr, $stream);
            if ($whereClause === null) {
                return; // Error already emitted
            }
        }
        
        // Emit delete event
        $stream->emit(new DeleteEvent(
            $event->data,
            $stream->getId(),
            $tableName,
            $whereClause
        ));
    }
    
    /**
     * Parse WHERE clause (same logic as UpdateParseGate)
     * 
     * @param string $whereStr
     * @param Stream $stream
     * @return WhereClause|null
     */
    private function parseWhere(string $whereStr, Stream $stream): ?WhereClause {
        // Check if this contains OR or AND combiners
        // BUT we need to be careful about BETWEEN...AND
        
        // If it contains BETWEEN, extract what's after the second value to check for combiners
        if (preg_match('/BETWEEN\s+.+?\s+AND\s+.+?(\s+(?:AND|OR)\s+|$)/i', $whereStr, $betweenMatch)) {
            $afterBetween = $betweenMatch[1];
            if (trim($afterBetween) === '') {
                // BETWEEN with no combiners after it
                return $this->parseWhereSimple($whereStr, $stream);
            }
        }
        
        // Check for OR (lowest precedence, parse first)
        $orPos = stripos($whereStr, ' OR ');
        if ($orPos !== false) {
            // Check if this OR is inside a BETWEEN
            $betweenPos = stripos($whereStr, 'BETWEEN');
            if ($betweenPos === false || $orPos < $betweenPos) {
                return $this->parseWhereCompound($whereStr, 'OR', $stream);
            }
        }
        
        // Check for AND (but not if it's part of BETWEEN...AND)
        $andPos = stripos($whereStr, ' AND ');
        if ($andPos !== false) {
            // Check if this AND is part of BETWEEN
            $betweenPos = stripos($whereStr, 'BETWEEN');
            if ($betweenPos === false) {
                // No BETWEEN, so this is a combiner
                return $this->parseWhereCompound($whereStr, 'AND', $stream);
            } else {
                // There's a BETWEEN - check if AND is part of it
                if (preg_match('/BETWEEN\s+.+?\s+AND\s+.+?(\s|$)/i', $whereStr, $match)) {
                    $betweenEnd = strpos($whereStr, $match[0]) + strlen($match[0]);
                    // If there's more text after BETWEEN...AND, it might be another AND
                    if ($betweenEnd < strlen($whereStr)) {
                        $remainder = substr($whereStr, $betweenEnd);
                        if (stripos($remainder, ' AND ') !== false || stripos($remainder, ' OR ') !== false) {
                            return $this->parseWhereCompound($whereStr, 'AND', $stream);
                        }
                    }
                }
                // This AND is part of BETWEEN, treat as simple
                return $this->parseWhereSimple($whereStr, $stream);
            }
        }
        
        // Simple condition
        return $this->parseWhereSimple($whereStr, $stream);
    }
    
    /**
     * Parse compound WHERE clause (AND/OR)
     * 
     * @param string $whereStr
     * @param string $combiner AND or OR
     * @param Stream $stream
     * @return WhereClause|null
     */
    private function parseWhereCompound(string $whereStr, string $combiner, Stream $stream): ?WhereClause {
        // Split by combiner (case-insensitive)
        $parts = preg_split('/\s+' . $combiner . '\s+/i', $whereStr, 2);
        
        if (count($parts) !== 2) {
            $stream->emit(new Event(
                "RESULT:ERROR: Invalid compound WHERE clause: {$whereStr}",
                $stream->getId()
            ));
            return null;
        }
        
        // Parse left and right conditions recursively
        $left = $this->parseWhere(trim($parts[0]), $stream);
        if ($left === null) return null;
        
        $right = $this->parseWhere(trim($parts[1]), $stream);
        if ($right === null) return null;
        
        return WhereClause::compound($left, $combiner, $right);
    }
    
    /**
     * Parse simple WHERE condition
     * 
     * @param string $whereStr
     * @param Stream $stream
     * @return WhereClause|null
     */
    private function parseWhereSimple(string $whereStr, Stream $stream): ?WhereClause {
        // IS NULL / IS NOT NULL
        if (preg_match('/^(\w+)\s+IS\s+NOT\s+NULL$/i', $whereStr, $matches)) {
            return WhereClause::simple($matches[1], 'IS NOT NULL', null);
        }
        if (preg_match('/^(\w+)\s+IS\s+NULL$/i', $whereStr, $matches)) {
            return WhereClause::simple($matches[1], 'IS NULL', null);
        }
        
        // BETWEEN
        if (preg_match('/^(\w+)\s+BETWEEN\s+(.+?)\s+AND\s+(.+)$/i', $whereStr, $matches)) {
            $column = $matches[1];
            $min = $this->parseValue(trim($matches[2]));
            $max = $this->parseValue(trim($matches[3]));
            return WhereClause::simple($column, 'BETWEEN', ['min' => $min, 'max' => $max]);
        }
        
        // IN
        if (preg_match('/^(\w+)\s+IN\s+\((.+)\)$/i', $whereStr, $matches)) {
            $column = $matches[1];
            $valuesPart = $matches[2];
            $values = array_map(function($v) {
                return $this->parseValue(trim($v));
            }, explode(',', $valuesPart));
            return WhereClause::simple($column, 'IN', $values);
        }
        
        // LIKE
        if (preg_match('/^(\w+)\s+LIKE\s+(.+)$/i', $whereStr, $matches)) {
            $column = $matches[1];
            $pattern = $this->parseValue(trim($matches[2]));
            return WhereClause::simple($column, 'LIKE', $pattern);
        }
        
        // Standard comparison operators
        $operators = ['!=', '<=', '>=', '<>', '=', '<', '>'];
        
        foreach ($operators as $op) {
            $escapedOp = preg_quote($op, '/');
            if (preg_match('/^(\w+)\s*' . $escapedOp . '\s*(.+)$/i', $whereStr, $matches)) {
                $column = $matches[1];
                $value = $this->parseValue(trim($matches[2]));
                return WhereClause::simple($column, $op, $value);
            }
        }
        
        $stream->emit(new Event(
            "RESULT:ERROR: Invalid WHERE condition: {$whereStr}",
            $stream->getId()
        ));
        return null;
    }
    
    /**
     * Parse a value from SQL
     * 
     * @param string $valueStr
     * @return mixed
     */
    private function parseValue(string $valueStr): mixed {
        // NULL
        if (strtoupper($valueStr) === 'NULL') {
            return null;
        }
        
        // String (quoted)
        if (preg_match('/^["\'](.+)["\']$/', $valueStr, $matches)) {
            return $matches[1];
        }
        
        // Integer
        if (preg_match('/^-?\d+$/', $valueStr)) {
            return (int)$valueStr;
        }
        
        // Float
        if (preg_match('/^-?\d+\.\d+$/', $valueStr)) {
            return (float)$valueStr;
        }
        
        // Unquoted string
        return $valueStr;
    }
}
