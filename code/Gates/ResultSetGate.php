<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\RowSetEvent;
use SISODatabase\Gate;
use SISODatabase\Stream;

/**
 * ResultSetGate - Formats query results for display.
 * 
 * Converts RowSetEvent into a formatted result string.
 */
class ResultSetGate extends Gate {
    /**
     * Match RowSetEvent instances
     * 
     * @param Event $event
     * @return bool
     */
    public function matches(Event $event): bool {
        return $event instanceof RowSetEvent;
    }
    
    /**
     * Format rowset as result
     * 
     * @param Event $event
     * @param Stream $stream
     */
    public function transform(Event $event, Stream $stream): void {
        /** @var RowSetEvent $event */
        
        $rowCount = $event->count();
        $result = [];
        
        // Build result string
        if ($rowCount === 0) {
            $result[] = "0 rows returned";
        } else {
            $result[] = "{$rowCount} row" . ($rowCount !== 1 ? 's' : '') . " returned";
            $result[] = "";
            
            // Column headers
            $headers = implode("\t", $event->columnNames);
            $result[] = $headers;
            $result[] = str_repeat("-", strlen($headers) + (count($event->columnNames) * 3));
            
            // Rows
            foreach ($event->rows as $row) {
                $values = [];
                foreach ($event->columnNames as $col) {
                    $val = $row->get($col);
                    if ($val === null) {
                        $values[] = "NULL";
                    } else {
                        $values[] = $val;
                    }
                }
                $result[] = implode("\t", $values);
            }
        }
        
        $stream->emit(new Event(
            "RESULT:" . implode("\n", $result),
            $stream->getId()
        ));
    }
}
