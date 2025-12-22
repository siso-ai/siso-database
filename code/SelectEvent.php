<?php

namespace SISODatabase;

/**
 * SelectEvent - Event carrying SELECT query information.
 * 
 * Contains parsed SELECT query metadata ready for execution.
 */
class SelectEvent extends Event {
    /**
     * Table name to select from
     */
    public readonly string $tableName;
    
    /**
     * Columns to select
     * Empty array means SELECT *
     * @var array<string>
     */
    public readonly array $columns;
    
    /**
     * Is this SELECT *?
     */
    public readonly bool $selectAll;
    
    /**
     * ORDER BY clause
     * @var array{column: string, direction: string}|null
     */
    public readonly ?array $orderBy;
    
    /**
     * LIMIT value
     */
    public readonly ?int $limit;
    
    /**
     * OFFSET value
     */
    public readonly ?int $offset;
    
    /**
     * DISTINCT flag
     */
    public readonly bool $distinct;
    
    /**
     * WHERE clause (for filtering)
     */
    public readonly ?WhereClause $whereClause;
    
    /**
     * Create a select event
     * 
     * @param string $data Original SQL
     * @param string $streamId Stream ID
     * @param string $tableName Target table
     * @param array $columns Columns to select (empty = *)
     * @param array|null $orderBy Order by clause
     * @param int|null $limit Limit value
     * @param int|null $offset Offset value
     * @param bool $distinct Distinct flag
     * @param WhereClause|null $whereClause WHERE clause
     */
    public function __construct(
        string $data,
        string $streamId,
        string $tableName,
        array $columns = [],
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
        bool $distinct = false,
        ?WhereClause $whereClause = null
    ) {
        parent::__construct($data, $streamId);
        $this->tableName = $tableName;
        $this->columns = $columns;
        $this->selectAll = empty($columns);
        $this->orderBy = $orderBy;
        $this->limit = $limit;
        $this->offset = $offset;
        $this->distinct = $distinct;
        $this->whereClause = $whereClause;
    }
}
