<?php

namespace SISODatabase;

/**
 * Event - Immutable data packet that flows through the stream.
 * 
 * Events carry data (typically SQL strings or intermediate representations)
 * through the gate pipeline. They track their transformation history and
 * which gates have processed or rejected them.
 */
class Event {
    /**
     * The data payload - typically a SQL string or intermediate result
     */
    public readonly string $data;
    
    /**
     * Unique identifier for the stream processing this event
     */
    public readonly string $streamId;
    
    /**
     * List of gate class names that rejected this event
     */
    public array $rejectedBy = [];
    
    /**
     * Total number of gates in the current stream
     */
    public int $gatesInRoom = 0;
    
    /**
     * List of gate class names that successfully transformed this event
     */
    public array $transformedBy = [];
    
    /**
     * Detailed transformation history with before/after snapshots
     * Structure: [['gate' => string, 'before' => string, 'after' => string, 'timestamp' => float]]
     */
    public array $history = [];
    
    /**
     * Create a new event
     * 
     * @param string $data The data payload
     * @param string $streamId The stream processing this event
     */
    public function __construct(string $data, string $streamId) {
        $this->data = $data;
        $this->streamId = $streamId;
    }
    
    /**
     * Mark this event as rejected by a gate
     * 
     * @param string $gateName The class name of the rejecting gate
     */
    public function reject(string $gateName): void {
        if (!in_array($gateName, $this->rejectedBy)) {
            $this->rejectedBy[] = $gateName;
        }
    }
    
    /**
     * Check if this event has been rejected by all gates
     * 
     * @return bool True if rejected by all gates in the stream
     */
    public function isRejectedByAll(): bool {
        return count($this->rejectedBy) === $this->gatesInRoom;
    }
    
    /**
     * Track a transformation by a gate
     * 
     * @param string $gateName The gate that transformed this event
     * @param string $before The data before transformation
     * @param string $after The data after transformation
     * @param int $loggingLevel The logging verbosity level
     */
    public function track(string $gateName, string $before, string $after, int $loggingLevel): void {
        // Always track gate name (minimal)
        if ($loggingLevel >= LoggingLevel::MINIMAL) {
            $this->transformedBy[] = $gateName;
        }
        
        // Track before/after at DETAILED level
        if ($loggingLevel >= LoggingLevel::DETAILED) {
            $this->history[] = [
                'gate' => $gateName,
                'before' => $before,
                'after' => $after,
                'timestamp' => microtime(true)
            ];
        }
    }
    
    /**
     * Get the full transformation history
     * 
     * @return array Array of transformation records
     */
    public function getHistory(): array {
        return $this->history;
    }
    
    /**
     * Get the list of gates that transformed this event
     * 
     * @return array Array of gate class names
     */
    public function getTransformedBy(): array {
        return $this->transformedBy;
    }
    
    /**
     * Get a detailed error report for this event
     * Used when an event fails to match any gate
     * 
     * @return string Formatted error message
     */
    public function getDetailedError(): string {
        $lines = [];
        $lines[] = "=== EVENT PROCESSING ERROR ===";
        $lines[] = "Input: {$this->data}";
        $lines[] = "Stream ID: {$this->streamId}";
        $lines[] = "Gates attempted: {$this->gatesInRoom}";
        $lines[] = "Rejected by:";
        
        foreach ($this->rejectedBy as $gate) {
            $lines[] = "  - {$gate}";
        }
        
        if (!empty($this->transformedBy)) {
            $lines[] = "\nSuccessfully transformed by:";
            foreach ($this->transformedBy as $gate) {
                $lines[] = "  - {$gate}";
            }
        }
        
        $lines[] = "\nSuggestion: Check SQL syntax or add appropriate gate to handle this input.";
        $lines[] = "==============================";
        
        return implode("\n", $lines);
    }
}
