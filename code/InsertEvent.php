<?php

namespace SISODatabase;

/**
 * InsertEvent - Event carrying INSERT statement data.
 * 
 * Extends base Event to include parsed insert information.
 * Used when INSERT has been parsed successfully.
 */
class InsertEvent extends Event {
    /**
     * Table name to insert into
     */
    public readonly string $tableName;
    
    /**
     * Values to insert (array for single row, array of arrays for batch)
     * For Phase 1: indexed array of values [val1, val2, val3]
     * For Phase 2: associative array [col => val, ...]
     * For Phase 3: array of value arrays
     */
    public readonly array $values;
    
    /**
     * Whether column names were specified (Phase 2)
     */
    public readonly bool $hasColumnNames;
    
    /**
     * Column names if specified (Phase 2)
     * @var array|null
     */
    public readonly ?array $columns;
    
    /**
     * Whether this is a batch insert (Phase 3)
     */
    public readonly bool $isBatch;
    
    /**
     * Create an insert event
     * 
     * @param string $data Original SQL
     * @param string $streamId Stream ID
     * @param string $tableName Table name
     * @param array $values Values to insert
     * @param bool $hasColumnNames Whether columns were specified
     * @param array|null $columns Column names (if specified)
     * @param bool $isBatch Whether this is a batch insert
     */
    public function __construct(
        string $data,
        string $streamId,
        string $tableName,
        array $values,
        bool $hasColumnNames = false,
        ?array $columns = null,
        bool $isBatch = false
    ) {
        parent::__construct($data, $streamId);
        $this->tableName = $tableName;
        $this->values = $values;
        $this->hasColumnNames = $hasColumnNames;
        $this->columns = $columns;
        $this->isBatch = $isBatch;
    }
}
