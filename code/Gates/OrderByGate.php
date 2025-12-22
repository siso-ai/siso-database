<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\RowSetEvent;
use SISODatabase\Gate;
use SISODatabase\Stream;

/**
 * OrderByGate - Sorts query results.
 * 
 * Takes a RowSetEvent and sorts rows by specified column.
 */
class OrderByGate extends Gate {
    /**
     * Match RowSetEvent with ORDER BY clause
     * 
     * @param Event $event
     * @return bool
     */
    public function matches(Event $event): bool {
        if (!($event instanceof RowSetEvent)) {
            return false;
        }
        
        // Don't reprocess if already through the pipeline
        if (in_array($event->data, ['sorted', 'limited'])) {
            return false;
        }
        
        return $event->selectEvent && $event->selectEvent->orderBy !== null;
    }
    
    /**
     * Sort the rows
     * 
     * @param Event $event
     * @param Stream $stream
     */
    public function transform(Event $event, Stream $stream): void {
        /** @var RowSetEvent $event */
        
        $orderBy = $event->selectEvent->orderBy;
        $column = $orderBy['column'];
        $direction = $orderBy['direction'];
        
        // Validate column exists
        if (!empty($event->rows)) {
            $firstRow = $event->rows[0];
            if (!$firstRow->has($column)) {
                $stream->emit(new Event(
                    "RESULT:ERROR: ORDER BY column '{$column}' does not exist",
                    $stream->getId()
                ));
                return;
            }
        }
        
        // Sort rows
        $sortedRows = $event->rows;
        
        usort($sortedRows, function($a, $b) use ($column, $direction) {
            $valA = $a->get($column);
            $valB = $b->get($column);
            
            // Handle NULL values (NULLs last)
            if ($valA === null && $valB === null) return 0;
            if ($valA === null) return 1;
            if ($valB === null) return -1;
            
            // Numeric comparison if both are numeric
            if (is_numeric($valA) && is_numeric($valB)) {
                $cmp = $valA <=> $valB;
            } else {
                // String comparison
                $cmp = strcmp((string)$valA, (string)$valB);
            }
            
            return $direction === 'DESC' ? -$cmp : $cmp;
        });
        
        // Emit sorted rowset
        $stream->emit(new RowSetEvent(
            "sorted",
            $stream->getId(),
            $sortedRows,
            $event->columnNames,
            $event->selectEvent
        ));
    }
}
