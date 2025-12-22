<?php

namespace SISODatabase;

/**
 * ColumnDefinition - Represents a single column in a table.
 * 
 * Contains column name, type, and constraints (PRIMARY KEY, NOT NULL, DEFAULT).
 */
class ColumnDefinition {
    /**
     * Column name
     */
    public readonly string $name;
    
    /**
     * Column type (INTEGER, TEXT, REAL, BLOB)
     */
    public readonly string $type;
    
    /**
     * Is this the primary key?
     */
    public bool $primaryKey = false;
    
    /**
     * Is NULL allowed?
     */
    public bool $notNull = false;
    
    /**
     * Default value (if any)
     */
    public mixed $defaultValue = null;
    
    /**
     * Create a new column definition
     * 
     * @param string $name Column name
     * @param string $type Column type (INTEGER, TEXT, REAL, BLOB)
     * @param bool $primaryKey Is this the primary key?
     * @param bool $notNull Is NOT NULL constraint active?
     * @param mixed $defaultValue Default value (if any)
     */
    public function __construct(
        string $name, 
        string $type = 'TEXT',
        bool $primaryKey = false,
        bool $notNull = false,
        mixed $defaultValue = null
    ) {
        $this->name = $name;
        $this->type = strtoupper($type);
        $this->primaryKey = $primaryKey;
        $this->notNull = $notNull;
        $this->defaultValue = $defaultValue;
        
        // PRIMARY KEY implies NOT NULL
        if ($this->primaryKey) {
            $this->notNull = true;
        }
    }
    
    /**
     * Set as primary key
     * 
     * @return self
     */
    public function setPrimaryKey(): self {
        $this->primaryKey = true;
        $this->notNull = true; // PRIMARY KEY implies NOT NULL
        return $this;
    }
    
    /**
     * Set NOT NULL constraint
     * 
     * @return self
     */
    public function setNotNull(): self {
        $this->notNull = true;
        return $this;
    }
    
    /**
     * Set default value
     * 
     * @param mixed $value Default value
     * @return self
     */
    public function setDefault(mixed $value): self {
        $this->defaultValue = $value;
        return $this;
    }
    
    /**
     * Check if column allows NULL
     * 
     * @return bool
     */
    public function allowsNull(): bool {
        return !$this->notNull;
    }
    
    /**
     * Check if column has a default value
     * 
     * @return bool
     */
    public function hasDefault(): bool {
        return $this->defaultValue !== null;
    }
    
    /**
     * String representation for debugging
     * 
     * @return string
     */
    public function __toString(): string {
        $str = "{$this->name} {$this->type}";
        if ($this->primaryKey) $str .= " PRIMARY KEY";
        if ($this->notNull && !$this->primaryKey) $str .= " NOT NULL";
        if ($this->defaultValue !== null) $str .= " DEFAULT {$this->defaultValue}";
        return $str;
    }
}
