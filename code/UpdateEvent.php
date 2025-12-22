<?php

namespace SISODatabase;

/**
 * UpdateEvent - Event carrying UPDATE operation data.
 * 
 * Contains parsed UPDATE information ready for execution.
 */
class UpdateEvent extends Event {
    /**
     * Table name to update
     */
    public readonly string $tableName;
    
    /**
     * Column updates
     * Array of column => value pairs
     * @var array<string, mixed>
     */
    public readonly array $updates;
    
    /**
     * WHERE clause (optional)
     */
    public readonly ?WhereClause $whereClause;
    
    /**
     * Create an update event
     * 
     * @param string $data Original SQL
     * @param string $streamId Stream ID
     * @param string $tableName Target table
     * @param array $updates Column => value pairs
     * @param WhereClause|null $whereClause Optional WHERE clause
     */
    public function __construct(
        string $data,
        string $streamId,
        string $tableName,
        array $updates,
        ?WhereClause $whereClause = null
    ) {
        parent::__construct($data, $streamId);
        $this->tableName = $tableName;
        $this->updates = $updates;
        $this->whereClause = $whereClause;
    }
}
