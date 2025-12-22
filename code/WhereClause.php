<?php

namespace SISODatabase;

/**
 * WhereClause - Represents a WHERE condition.
 * 
 * Can be a simple comparison or a compound condition with AND/OR.
 */
class WhereClause {
    /**
     * Column name (for simple conditions)
     */
    public readonly ?string $column;
    
    /**
     * Comparison operator
     * =, !=, <, >, <=, >=, IN, LIKE, BETWEEN, IS NULL, IS NOT NULL
     */
    public readonly ?string $operator;
    
    /**
     * Comparison value (for simple conditions)
     */
    public readonly mixed $value;
    
    /**
     * Left sub-condition (for compound conditions)
     */
    public readonly ?WhereClause $left;
    
    /**
     * Right sub-condition (for compound conditions)
     */
    public readonly ?WhereClause $right;
    
    /**
     * Logical combiner (AND, OR)
     */
    public readonly ?string $combiner;
    
    /**
     * Is this a simple condition (not compound)?
     */
    public readonly bool $isSimple;
    
    /**
     * Create a simple WHERE condition
     * 
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Comparison value
     */
    private function __construct(
        ?string $column = null,
        ?string $operator = null,
        mixed $value = null,
        ?WhereClause $left = null,
        ?WhereClause $right = null,
        ?string $combiner = null,
        bool $isSimple = true
    ) {
        $this->column = $column;
        $this->operator = $operator ? strtoupper($operator) : null;
        $this->value = $value;
        $this->left = $left;
        $this->right = $right;
        $this->combiner = $combiner ? strtoupper($combiner) : null;
        $this->isSimple = $isSimple;
    }
    
    /**
     * Create a simple WHERE condition
     * 
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Comparison value
     * @return WhereClause
     */
    public static function simple(string $column, string $operator, mixed $value): WhereClause {
        return new self($column, $operator, $value, null, null, null, true);
    }
    
    /**
     * Create a compound WHERE condition
     * 
     * @param WhereClause $left Left condition
     * @param string $combiner AND or OR
     * @param WhereClause $right Right condition
     * @return WhereClause
     */
    public static function compound(
        WhereClause $left,
        string $combiner,
        WhereClause $right
    ): WhereClause {
        return new self(null, null, null, $left, $right, $combiner, false);
    }
    
    /**
     * Evaluate this condition against a row
     * 
     * @param Row $row The row to test
     * @return bool True if row matches condition
     */
    public function evaluate(Row $row): bool {
        if ($this->isSimple) {
            return $this->evaluateSimple($row);
        } else {
            return $this->evaluateCompound($row);
        }
    }
    
    /**
     * Evaluate a simple condition
     * 
     * @param Row $row
     * @return bool
     */
    private function evaluateSimple(Row $row): bool {
        $columnValue = $row->get($this->column);
        
        switch ($this->operator) {
            case '=':
                return $columnValue == $this->value;
                
            case '!=':
            case '<>':
                return $columnValue != $this->value;
                
            case '<':
                return $columnValue < $this->value;
                
            case '>':
                return $columnValue > $this->value;
                
            case '<=':
                return $columnValue <= $this->value;
                
            case '>=':
                return $columnValue >= $this->value;
                
            case 'IS NULL':
                return $columnValue === null;
                
            case 'IS NOT NULL':
                return $columnValue !== null;
                
            case 'IN':
                // $this->value should be array
                return in_array($columnValue, (array)$this->value);
            
            case 'LIKE':
                // Convert SQL LIKE pattern to regex
                $pattern = str_replace(['%', '_'], ['.*', '.'], preg_quote((string)$this->value, '/'));
                return preg_match("/^{$pattern}$/i", (string)$columnValue) === 1;
            
            case 'BETWEEN':
                // $this->value should be ['min' => x, 'max' => y]
                return $columnValue >= $this->value['min'] && $columnValue <= $this->value['max'];
                
            default:
                return false;
        }
    }
    
    /**
     * Evaluate a compound condition
     * 
     * @param Row $row
     * @return bool
     */
    private function evaluateCompound(Row $row): bool {
        $leftResult = $this->left->evaluate($row);
        $rightResult = $this->right->evaluate($row);
        
        if ($this->combiner === 'AND') {
            return $leftResult && $rightResult;
        } elseif ($this->combiner === 'OR') {
            return $leftResult || $rightResult;
        }
        
        return false;
    }
    
    /**
     * String representation for debugging
     * 
     * @return string
     */
    public function __toString(): string {
        if ($this->isSimple) {
            if ($this->operator === 'IS NULL' || $this->operator === 'IS NOT NULL') {
                return "{$this->column} {$this->operator}";
            }
            $value = is_string($this->value) ? "'{$this->value}'" : 
                    (is_array($this->value) ? json_encode($this->value) : $this->value);
            return "{$this->column} {$this->operator} {$value}";
        } else {
            return "({$this->left} {$this->combiner} {$this->right})";
        }
    }
}
