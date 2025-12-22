<?php

namespace SISODatabase;

/**
 * SchemaEvent - Event carrying table schema information.
 * 
 * Extends base Event to include parsed table schema.
 * Used when CREATE TABLE has been parsed successfully.
 */
class SchemaEvent extends Event {
    /**
     * Parsed table schema
     */
    public readonly TableSchema $schema;
    
    /**
     * Create a schema event
     * 
     * @param string $data Original SQL or result message
     * @param string $streamId Stream ID
     * @param TableSchema $schema The parsed schema
     */
    public function __construct(string $data, string $streamId, TableSchema $schema) {
        parent::__construct($data, $streamId);
        $this->schema = $schema;
    }
}
