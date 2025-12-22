<?php

namespace SISODatabase\Gates;

use SISODatabase\Event;
use SISODatabase\Gate;
use SISODatabase\Stream;

/**
 * ErrorGate - Handles events that no other gate matched.
 * 
 * This gate should be registered LAST in the pipeline. It catches
 * events that have been rejected by all other gates and produces
 * appropriate error messages.
 * 
 * Two modes:
 * - Development: Detailed error with suggestions
 * - Production: Simple error message
 */
class ErrorGate extends Gate {
    /**
     * Whether to use production mode (simple errors)
     */
    private bool $productionMode;
    
    /**
     * Create error gate
     * 
     * @param bool $productionMode True for production (simple errors), false for development (detailed)
     */
    public function __construct(bool $productionMode = false) {
        $this->productionMode = $productionMode;
    }
    
    /**
     * Match events that were rejected by ALL other gates
     * 
     * @param Event $event The event to check
     * @return bool True if event was rejected by all
     */
    public function matches(Event $event): bool {
        return $event->isRejectedByAll();
    }
    
    /**
     * Generate error message and emit as result
     * 
     * @param Event $event The rejected event
     * @param Stream $stream The stream to emit error to
     */
    public function transform(Event $event, Stream $stream): void {
        if ($this->productionMode) {
            // Production mode - simple error
            $error = "ERROR: Invalid SQL syntax";
        } else {
            // Development mode - detailed error
            $error = "ERROR:\n" . $event->getDetailedError();
        }
        
        // Emit error as result
        $stream->emit(new Event("RESULT:{$error}", $stream->getId()));
    }
}
