<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\RowSetEvent;
use SISODatabase\Gate;
use SISODatabase\Stream;

/**
 * FilterGate - Applies WHERE clause filtering to rows.
 * 
 * Takes a RowSetEvent and filters rows based on WHERE conditions.
 */
class FilterGate extends Gate {
    /**
     * Match RowSetEvent with WHERE clause
     * 
     * @param Event $event
     * @return bool
     */
    public function matches(Event $event): bool {
        if (!($event instanceof RowSetEvent)) {
            return false;
        }
        
        // Don't reprocess if already through the pipeline
        if (in_array($event->data, ['filtered', 'projected', 'distinct', 'sorted', 'limited'])) {
            return false;
        }
        
        return $event->selectEvent && $event->selectEvent->whereClause !== null;
    }
    
    /**
     * Filter rows based on WHERE clause
     * 
     * @param Event $event
     * @param Stream $stream
     */
    public function transform(Event $event, Stream $stream): void {
        /** @var RowSetEvent $event */
        
        $whereClause = $event->selectEvent->whereClause;
        $filteredRows = [];
        
        foreach ($event->rows as $row) {
            if ($whereClause->evaluate($row)) {
                $filteredRows[] = $row;
            }
        }
        
        // Emit filtered rowset
        $stream->emit(new RowSetEvent(
            "filtered",
            $stream->getId(),
            $filteredRows,
            $event->columnNames,
            $event->selectEvent
        ));
    }
}
