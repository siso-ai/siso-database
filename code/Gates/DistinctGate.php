<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\RowSetEvent;
use SISODatabase\Gate;
use SISODatabase\Stream;

/**
 * DistinctGate - Removes duplicate rows.
 * 
 * Takes a RowSetEvent and filters out duplicate rows based on all column values.
 */
class DistinctGate extends Gate {
    /**
     * Match RowSetEvent with DISTINCT flag
     * 
     * @param Event $event
     * @return bool
     */
    public function matches(Event $event): bool {
        if (!($event instanceof RowSetEvent)) {
            return false;
        }
        
        // Don't reprocess if already through the pipeline
        if (in_array($event->data, ['distinct', 'sorted', 'limited'])) {
            return false;
        }
        
        return $event->selectEvent && $event->selectEvent->distinct;
    }
    
    /**
     * Remove duplicate rows
     * 
     * @param Event $event
     * @param Stream $stream
     */
    public function transform(Event $event, Stream $stream): void {
        /** @var RowSetEvent $event */
        
        $uniqueRows = [];
        $seen = [];
        
        foreach ($event->rows as $row) {
            // Create a signature for this row based on all values
            $signature = json_encode($row->getValues());
            
            if (!isset($seen[$signature])) {
                $seen[$signature] = true;
                $uniqueRows[] = $row;
            }
        }
        
        $stream->emit(new RowSetEvent(
            "distinct",
            $stream->getId(),
            $uniqueRows,
            $event->columnNames,
            $event->selectEvent
        ));
    }
}
