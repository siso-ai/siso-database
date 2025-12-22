<?php

namespace SISODatabase;

/**
 * Table - Represents a database table with schema and data.
 * 
 * Stores both the table structure (schema) and the actual data (rows).
 */
class Table {
    /**
     * Table schema
     */
    public readonly TableSchema $schema;
    
    /**
     * Rows of data
     * @var array<int, Row>
     */
    private array $rows = [];
    
    /**
     * Auto-increment counter for primary key
     */
    private int $autoIncrement = 1;
    
    /**
     * Create a new table
     * 
     * @param TableSchema $schema The table structure
     */
    public function __construct(TableSchema $schema) {
        $this->schema = $schema;
    }
    
    /**
     * Insert a row into the table
     * 
     * @param Row $row The row to insert
     * @return int The row ID (index)
     */
    public function insert(Row $row): int {
        $this->rows[] = $row;
        return count($this->rows) - 1;
    }
    
    /**
     * Get a row by index
     * 
     * @param int $index Row index
     * @return Row|null
     */
    public function getRow(int $index): ?Row {
        return $this->rows[$index] ?? null;
    }
    
    /**
     * Get all rows
     * 
     * @return array<Row>
     */
    public function getAllRows(): array {
        return $this->rows;
    }
    
    /**
     * Get number of rows
     * 
     * @return int
     */
    public function count(): int {
        return count($this->rows);
    }
    
    /**
     * Delete all rows (keep schema)
     */
    public function truncate(): void {
        $this->rows = [];
        $this->autoIncrement = 1;
    }
    
    /**
     * Get the next auto-increment value
     * 
     * @return int
     */
    public function getNextAutoIncrement(): int {
        return $this->autoIncrement++;
    }
    
    /**
     * Find rows matching a condition
     * 
     * @param callable $predicate Function that takes a Row and returns bool
     * @return array<Row>
     */
    public function findRows(callable $predicate): array {
        return array_filter($this->rows, $predicate);
    }
    
    /**
     * Update rows matching a condition
     * 
     * @param array $updates Associative array of column => value
     * @param callable|null $predicate Optional filter (null = all rows)
     * @return int Number of rows updated
     */
    public function updateRows(array $updates, ?callable $predicate = null): int {
        $count = 0;
        
        foreach ($this->rows as $index => $row) {
            if ($predicate === null || $predicate($row)) {
                // Create new row with updated values
                $newValues = $row->values;
                foreach ($updates as $column => $value) {
                    $newValues[$column] = $value;
                }
                $this->rows[$index] = new Row($newValues);
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Delete rows matching a condition
     * 
     * @param callable|null $predicate Optional filter (null = all rows)
     * @return int Number of rows deleted
     */
    public function deleteRows(?callable $predicate = null): int {
        if ($predicate === null) {
            $count = count($this->rows);
            $this->truncate();
            return $count;
        }
        
        $originalCount = count($this->rows);
        $this->rows = array_values(array_filter($this->rows, function($row) use ($predicate) {
            return !$predicate($row);
        }));
        
        return $originalCount - count($this->rows);
    }
}
