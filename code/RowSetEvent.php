<?php

namespace SISODatabase;

/**
 * RowSetEvent - Event carrying a set of rows.
 * 
 * Used to pass query results through the gate pipeline.
 */
class RowSetEvent extends Event {
    /**
     * Rows from the query
     * @var array<Row>
     */
    public readonly array $rows;
    
    /**
     * Column names to display
     * @var array<string>
     */
    public readonly array $columnNames;
    
    /**
     * Original SELECT event (for context)
     */
    public readonly ?SelectEvent $selectEvent;
    
    /**
     * Create a rowset event
     * 
     * @param string $data Description or result message
     * @param string $streamId Stream ID
     * @param array $rows The rows
     * @param array $columnNames Columns to display
     * @param SelectEvent|null $selectEvent Original query
     */
    public function __construct(
        string $data,
        string $streamId,
        array $rows,
        array $columnNames,
        ?SelectEvent $selectEvent = null
    ) {
        parent::__construct($data, $streamId);
        $this->rows = $rows;
        $this->columnNames = $columnNames;
        $this->selectEvent = $selectEvent;
    }
    
    /**
     * Get row count
     * 
     * @return int
     */
    public function count(): int {
        return count($this->rows);
    }
}
