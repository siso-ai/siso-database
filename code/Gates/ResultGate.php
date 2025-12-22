<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\Gate;
use SISODatabase\Stream;

/**
 * ResultGate - Captures final results and stops processing.
 * 
 * This gate matches events prefixed with "RESULT:" and extracts
 * the actual result, storing it in the stream. This should always
 * be registered near the end of the gate pipeline.
 */
class ResultGate extends Gate {
    /**
     * Match events that start with "RESULT:"
     * 
     * @param Event $event The event to check
     * @return bool True if event is a result
     */
    public function matches(Event $event): bool {
        return str_starts_with($event->data, 'RESULT:');
    }
    
    /**
     * Extract the result and store it in the stream
     * 
     * @param Event $event The result event
     * @param Stream $stream The stream to store result in
     */
    public function transform(Event $event, Stream $stream): void {
        // Extract result (everything after "RESULT:")
        $result = substr($event->data, 7); // strlen("RESULT:") = 7
        
        // Store in stream
        $stream->setResult($result);
        
        // Don't emit any new events - processing stops here
    }
}
