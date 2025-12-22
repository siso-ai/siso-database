<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\Gate;
use SISODatabase\Stream;
use SISODatabase\SelectEvent;

/**
 * SelectParseGate - Parses SELECT statements.
 * 
 * Phase 1: SELECT * FROM table
 * Phase 2: SELECT col1, col2 FROM table
 * Phase 3: ORDER BY
 * Phase 4: LIMIT/OFFSET
 * Phase 5: DISTINCT
 */
class SelectParseGate extends Gate {
    /**
     * Match SELECT statements
     * 
     * @param Event $event
     * @return bool
     */
    public function matches(Event $event): bool {
        // Don't match if it's already a SelectEvent
        if ($event instanceof SelectEvent) {
            return false;
        }
        return preg_match('/^SELECT\s+/i', $event->data);
    }
    
    /**
     * Parse SELECT and emit SelectEvent
     * 
     * @param Event $event
     * @param Stream $stream
     */
    public function transform(Event $event, Stream $stream): void {
        $sql = trim($event->data);
        
        // Check for DISTINCT (Phase 5)
        $distinct = false;
        if (preg_match('/^SELECT\s+DISTINCT\s+/i', $sql)) {
            $distinct = true;
            $sql = preg_replace('/^SELECT\s+DISTINCT\s+/i', 'SELECT ', $sql);
        }
        
        // Parse basic SELECT ... FROM ...
        // Match either SELECT * or SELECT col1, col2, ...
        if (!preg_match('/^SELECT\s+(.+?)\s+FROM\s+(\w+)(.*)$/i', $sql, $matches)) {
            $stream->emit(new Event(
                "RESULT:ERROR: Invalid SELECT syntax: {$event->data}\n" .
                "Expected: SELECT columns FROM tablename",
                $stream->getId()
            ));
            return;
        }
        
        $columnsPart = trim($matches[1]);
        $tableName = $matches[2];
        $remainder = trim($matches[3]);
        
        // Parse columns
        $columns = [];
        if ($columnsPart === '*') {
            // SELECT *
            $columns = [];
        } else {
            // SELECT col1, col2, ...
            $columns = array_map('trim', explode(',', $columnsPart));
        }
        
        // Parse WHERE clause (Phases 1-4)
        $whereClause = null;
        if (preg_match('/WHERE\s+(.+?)(?:\s+ORDER\s+BY|\s+LIMIT|$)/is', $remainder, $whereMatches)) {
            $whereStr = trim($whereMatches[1]);
            $whereClause = $this->parseWhere($whereStr, $stream);
            if ($whereClause === null) {
                return; // Error already emitted
            }
            // Remove WHERE from remainder
            $remainder = preg_replace('/WHERE\s+.+?(?=\s+ORDER\s+BY|\s+LIMIT|$)/is', '', $remainder);
            $remainder = trim($remainder);
        }
        
        // Parse ORDER BY (Phase 3)
        $orderBy = null;
        if (preg_match('/ORDER\s+BY\s+(\w+)(?:\s+(ASC|DESC))?/i', $remainder, $orderMatches)) {
            $orderBy = [
                'column' => $orderMatches[1],
                'direction' => isset($orderMatches[2]) ? strtoupper($orderMatches[2]) : 'ASC'
            ];
            // Remove ORDER BY from remainder
            $remainder = preg_replace('/ORDER\s+BY\s+\w+(?:\s+(?:ASC|DESC))?/i', '', $remainder);
            $remainder = trim($remainder);
        }
        
        // Parse LIMIT and OFFSET (Phase 4)
        $limit = null;
        $offset = null;
        
        // LIMIT with OFFSET: LIMIT n OFFSET m
        if (preg_match('/LIMIT\s+(\d+)\s+OFFSET\s+(\d+)/i', $remainder, $limitMatches)) {
            $limit = (int)$limitMatches[1];
            $offset = (int)$limitMatches[2];
        }
        // LIMIT without OFFSET: LIMIT n
        elseif (preg_match('/LIMIT\s+(\d+)/i', $remainder, $limitMatches)) {
            $limit = (int)$limitMatches[1];
        }
        
        // Emit select event
        $stream->emit(new SelectEvent(
            $event->data,
            $stream->getId(),
            $tableName,
            $columns,
            $orderBy,
            $limit,
            $offset,
            $distinct,
            $whereClause
        ));
    }
    
    /**
     * Parse WHERE clause with support for all phases
     * 
     * @param string $whereStr
     * @param Stream $stream
     * @return WhereClause|null
     */
    private function parseWhere(string $whereStr, Stream $stream): ?\SISODatabase\WhereClause {
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
        
        // Phase 3: Check for OR (lowest precedence, parse first)
        // But not inside BETWEEN
        $orPos = stripos($whereStr, ' OR ');
        if ($orPos !== false) {
            // Check if this OR is inside a BETWEEN
            $betweenPos = stripos($whereStr, 'BETWEEN');
            if ($betweenPos === false || $orPos < $betweenPos) {
                return $this->parseWhereCompound($whereStr, 'OR', $stream);
            }
        }
        
        // Phase 2: Check for AND
        // But not if it's part of BETWEEN...AND
        $andPos = stripos($whereStr, ' AND ');
        if ($andPos !== false) {
            // Check if this AND is part of BETWEEN
            $betweenPos = stripos($whereStr, 'BETWEEN');
            if ($betweenPos === false) {
                // No BETWEEN, so this is a combiner
                return $this->parseWhereCompound($whereStr, 'AND', $stream);
            } else {
                // There's a BETWEEN - check if AND is part of it
                // Look for pattern: BETWEEN value AND value
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
        
        // Phase 1 & 4: Simple condition
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
    private function parseWhereCompound(string $whereStr, string $combiner, Stream $stream): ?\SISODatabase\WhereClause {
        // Split by combiner (case-insensitive)
        $parts = preg_split('/\s+' . $combiner . '\s+/i', $whereStr, 2);
        
        if (count($parts) !== 2) {
            $stream->emit(new \SISODatabase\Event(
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
        
        return \SISODatabase\WhereClause::compound($left, $combiner, $right);
    }
    
    /**
     * Parse simple WHERE condition
     * 
     * @param string $whereStr
     * @param Stream $stream
     * @return WhereClause|null
     */
    private function parseWhereSimple(string $whereStr, Stream $stream): ?\SISODatabase\WhereClause {
        // Phase 4: IS NULL / IS NOT NULL
        if (preg_match('/^(\w+)\s+IS\s+NOT\s+NULL$/i', $whereStr, $matches)) {
            return \SISODatabase\WhereClause::simple($matches[1], 'IS NOT NULL', null);
        }
        if (preg_match('/^(\w+)\s+IS\s+NULL$/i', $whereStr, $matches)) {
            return \SISODatabase\WhereClause::simple($matches[1], 'IS NULL', null);
        }
        
        // Phase 4: BETWEEN
        if (preg_match('/^(\w+)\s+BETWEEN\s+(.+?)\s+AND\s+(.+)$/i', $whereStr, $matches)) {
            $column = $matches[1];
            $min = $this->parseValue(trim($matches[2]));
            $max = $this->parseValue(trim($matches[3]));
            return \SISODatabase\WhereClause::simple($column, 'BETWEEN', ['min' => $min, 'max' => $max]);
        }
        
        // Phase 4: IN
        if (preg_match('/^(\w+)\s+IN\s+\((.+)\)$/i', $whereStr, $matches)) {
            $column = $matches[1];
            $valuesPart = $matches[2];
            $values = array_map(function($v) {
                return $this->parseValue(trim($v));
            }, explode(',', $valuesPart));
            return \SISODatabase\WhereClause::simple($column, 'IN', $values);
        }
        
        // Phase 4: LIKE
        if (preg_match('/^(\w+)\s+LIKE\s+(.+)$/i', $whereStr, $matches)) {
            $column = $matches[1];
            $pattern = $this->parseValue(trim($matches[2]));
            return \SISODatabase\WhereClause::simple($column, 'LIKE', $pattern);
        }
        
        // Phase 1: Standard comparison operators
        // Try operators in order: !=, <=, >=, <>, =, <, >
        $operators = ['!=', '<=', '>=', '<>', '=', '<', '>'];
        
        foreach ($operators as $op) {
            $escapedOp = preg_quote($op, '/');
            if (preg_match('/^(\w+)\s*' . $escapedOp . '\s*(.+)$/i', $whereStr, $matches)) {
                $column = $matches[1];
                $value = $this->parseValue(trim($matches[2]));
                return \SISODatabase\WhereClause::simple($column, $op, $value);
            }
        }
        
        $stream->emit(new \SISODatabase\Event(
            "RESULT:ERROR: Invalid WHERE condition: {$whereStr}",
            $stream->getId()
        ));
        return null;
    }
    
    /**
     * Parse a value from WHERE clause
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
