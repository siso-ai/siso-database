<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\RowSetEvent;
use SISODatabase\Gate;
use SISODatabase\Stream;

/**
 * LimitGate - Limits and offsets query results.
 * 
 * Takes a RowSetEvent and applies LIMIT/OFFSET.
 */
class LimitGate extends Gate {
    /**
     * Match RowSetEvent with LIMIT or OFFSET
     * 
     * @param Event $event
     * @return bool
     */
    public function matches(Event $event): bool {
        if (!($event instanceof RowSetEvent)) {
            return false;
        }
        
        // Don't reprocess if already limited
        if ($event->data === 'limited') {
            return false;
        }
        
        return $event->selectEvent && 
               ($event->selectEvent->limit !== null || $event->selectEvent->offset !== null);
    }
    
    /**
     * Apply LIMIT and OFFSET
     * 
     * @param Event $event
     * @param Stream $stream
     */
    public function transform(Event $event, Stream $stream): void {
        /** @var RowSetEvent $event */
        
        $limit = $event->selectEvent->limit;
        $offset = $event->selectEvent->offset ?? 0;
        
        // Apply offset and limit
        $limitedRows = array_slice($event->rows, $offset, $limit);
        
        $stream->emit(new RowSetEvent(
            "limited",
            $stream->getId(),
            $limitedRows,
            $event->columnNames,
            $event->selectEvent
        ));
    }
}
