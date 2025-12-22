<?php

namespace SISODatabase;

/**
 * DeleteEvent - Event carrying DELETE operation data.
 * 
 * Contains parsed DELETE information ready for execution.
 */
class DeleteEvent extends Event {
    /**
     * Table name to delete from
     */
    public readonly string $tableName;
    
    /**
     * WHERE clause (optional)
     */
    public readonly ?WhereClause $whereClause;
    
    /**
     * Create a delete event
     * 
     * @param string $data Original SQL
     * @param string $streamId Stream ID
     * @param string $tableName Target table
     * @param WhereClause|null $whereClause Optional WHERE clause
     */
    public function __construct(
        string $data,
        string $streamId,
        string $tableName,
        ?WhereClause $whereClause = null
    ) {
        parent::__construct($data, $streamId);
        $this->tableName = $tableName;
        $this->whereClause = $whereClause;
    }
}
