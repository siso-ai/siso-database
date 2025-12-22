<?php

namespace SISODatabase;

/**
 * Row - Represents a single row of data in a table.
 * 
 * Stores column values as an associative array.
 * Immutable once created.
 */
class Row {
    /**
     * Column values indexed by column name
     * @var array<string, mixed>
     */
    public readonly array $values;
    
    /**
     * Create a new row
     * 
     * @param array $values Column name => value pairs
     */
    public function __construct(array $values = []) {
        $this->values = $values;
    }
    
    /**
     * Get a column value
     * 
     * @param string $column Column name
     * @return mixed Column value or null if not set
     */
    public function get(string $column): mixed {
        return $this->values[$column] ?? null;
    }
    
    /**
     * Check if column exists in row
     * 
     * @param string $column Column name
     * @return bool
     */
    public function has(string $column): bool {
        return array_key_exists($column, $this->values);
    }
    
    /**
     * Get all column names
     * 
     * @return array
     */
    public function getColumns(): array {
        return array_keys($this->values);
    }
    
    /**
     * Get all values
     * 
     * @return array
     */
    public function getValues(): array {
        return $this->values;
    }
    
    /**
     * Create a new row with additional value
     * (Immutable pattern - returns new Row)
     * 
     * @param string $column Column name
     * @param mixed $value Column value
     * @return Row New row with added value
     */
    public function with(string $column, mixed $value): Row {
        $newValues = $this->values;
        $newValues[$column] = $value;
        return new Row($newValues);
    }
    
    /**
     * String representation for debugging
     * 
     * @return string
     */
    public function __toString(): string {
        $pairs = [];
        foreach ($this->values as $col => $val) {
            if (is_null($val)) {
                $displayVal = 'NULL';
            } elseif (is_string($val)) {
                $displayVal = "'{$val}'";
            } else {
                $displayVal = $val;
            }
            $pairs[] = "{$col}={$displayVal}";
        }
        return "Row(" . implode(', ', $pairs) . ")";
    }
}
