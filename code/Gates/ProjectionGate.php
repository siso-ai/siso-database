<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\RowSetEvent;
use SISODatabase\Row;
use SISODatabase\Gate;
use SISODatabase\Stream;

/**
 * ProjectionGate - Projects specific columns from rows.
 * 
 * Filters row data to include only requested columns.
 * Only processes when specific columns are requested (not SELECT *).
 */
class ProjectionGate extends Gate {
    /**
     * Match RowSetEvent with specific column selection
     * 
     * @param Event $event
     * @return bool
     */
    public function matches(Event $event): bool {
        if (!($event instanceof RowSetEvent)) {
            return false;
        }
        
        // Don't reprocess if already through the pipeline
        if (in_array($event->data, ['projected', 'distinct', 'sorted', 'limited'])) {
            return false;
        }
        
        // Only project if not SELECT * (i.e., specific columns requested)
        return $event->selectEvent && !$event->selectEvent->selectAll;
    }
    
    /**
     * Project columns from rows
     * 
     * @param Event $event
     * @param Stream $stream
     */
    public function transform(Event $event, Stream $stream): void {
        /** @var RowSetEvent $event */
        
        $projectedRows = [];
        
        foreach ($event->rows as $row) {
            $newValues = [];
            
            // Keep only the requested columns
            foreach ($event->columnNames as $col) {
                $newValues[$col] = $row->get($col);
            }
            
            $projectedRows[] = new Row($newValues);
        }
        
        // Emit projected rowset
        $stream->emit(new RowSetEvent(
            "projected",
            $stream->getId(),
            $projectedRows,
            $event->columnNames,
            $event->selectEvent
        ));
    }
}
