<?php

namespace SISODatabase;

/**
 * Stream - Orchestrates event flow through the gate pipeline.
 * 
 * The stream:
 * 1. Maintains a queue of events to process
 * 2. Manages the registered gates
 * 3. Processes events through gates in order
 * 4. Tracks results and rejected events
 * 5. Provides logging and debugging capabilities
 */
class Stream {
    /**
     * Unique identifier for this stream
     */
    private string $id;
    
    /**
     * Queue of events waiting to be processed
     */
    private array $events = [];
    
    /**
     * Registered gates in processing order
     */
    private array $gates = [];
    
    /**
     * Events that were rejected by all gates
     */
    private array $rejectedEvents = [];
    
    /**
     * Final result (if any)
     */
    private ?string $result = null;
    
    /**
     * Logging verbosity level
     */
    private int $loggingLevel = LoggingLevel::MINIMAL;
    
    /**
     * Maximum iterations to prevent infinite loops
     */
    private int $maxIterations = 1000;
    
    /**
     * Current iteration count
     */
    private int $iterations = 0;
    
    /**
     * Parent stream (for hierarchical processing)
     */
    private ?Stream $parent = null;
    
    /**
     * Gate class that created this child stream
     */
    private ?string $parentGate = null;
    
    /**
     * Create a new stream
     */
    public function __construct() {
        $this->id = uniqid('stream_', true);
    }
    
    /**
     * Get the unique ID of this stream
     * 
     * @return string The stream ID
     */
    public function getId(): string {
        return $this->id;
    }
    
    /**
     * Set the logging level
     * 
     * @param int $level One of LoggingLevel constants
     */
    public function setLoggingLevel(int $level): void {
        $this->loggingLevel = $level;
    }
    
    /**
     * Get the current logging level
     * 
     * @return int Current logging level
     */
    public function getLoggingLevel(): int {
        return $this->loggingLevel;
    }
    
    /**
     * Set maximum iterations allowed
     * 
     * @param int $max Maximum iteration count
     */
    public function setMaxIterations(int $max): void {
        $this->maxIterations = $max;
    }
    
    /**
     * Register a gate in the processing pipeline
     * Order matters - gates are processed in registration order
     * 
     * @param Gate $gate The gate to register
     */
    public function registerGate(Gate $gate): void {
        $this->gates[] = $gate;
    }
    
    /**
     * Get all registered gates
     * 
     * @return array Array of Gate objects
     */
    public function getGates(): array {
        return $this->gates;
    }
    
    /**
     * Emit an event into the stream
     * 
     * @param Event $event The event to emit
     */
    public function emit(Event $event): void {
        $this->events[] = $event;
    }
    
    /**
     * Process all events in the queue through the gate pipeline
     * 
     * @throws \RuntimeException If max iterations exceeded
     */
    public function process(): void {
        $this->iterations = 0;
        
        while (!empty($this->events) && $this->iterations < $this->maxIterations) {
            $this->iterations++;
            
            // Get next event
            $event = array_shift($this->events);
            
            // Set gate count for rejection tracking
            $event->gatesInRoom = count($this->gates);
            
            // Try each gate in order
            $processed = false;
            foreach ($this->gates as $gate) {
                if ($gate->matches($event)) {
                    // Gate matches - transform the event
                    $before = $event->data;
                    $gate->transform($event, $this);
                    
                    // Track the transformation
                    $event->track(
                        $gate->getName(),
                        $before,
                        $event->data,
                        $this->loggingLevel
                    );
                    
                    $processed = true;
                    break; // Only first matching gate processes
                }
                
                // Gate didn't match - mark as rejected
                $event->reject($gate->getName());
            }
            
            // If no gate processed it and it's rejected by all, save it
            if (!$processed && $event->isRejectedByAll()) {
                $this->rejectedEvents[] = $event;
            }
        }
        
        // Check for infinite loop
        if ($this->iterations >= $this->maxIterations) {
            throw new \RuntimeException(
                "Stream exceeded maximum iterations ({$this->maxIterations}). " .
                "Possible infinite loop detected."
            );
        }
    }
    
    /**
     * Get the final result
     * 
     * @return string|null The result or null if none
     */
    public function getResult(): ?string {
        return $this->result;
    }
    
    /**
     * Set the final result
     * Used by ResultGate
     * 
     * @param string $result The final result
     */
    public function setResult(string $result): void {
        $this->result = $result;
    }
    
    /**
     * Get all rejected events
     * 
     * @return array Array of rejected Event objects
     */
    public function getRejectedEvents(): array {
        return $this->rejectedEvents;
    }
    
    /**
     * Get the number of iterations used
     * 
     * @return int Iteration count
     */
    public function getIterations(): int {
        return $this->iterations;
    }
    
    /**
     * Set parent stream for hierarchical processing
     * 
     * @param Stream $parent The parent stream
     * @param string $gateName The gate that created this stream
     */
    public function setParent(Stream $parent, string $gateName): void {
        $this->parent = $parent;
        $this->parentGate = $gateName;
    }
    
    /**
     * Get the parent stream
     * 
     * @return Stream|null Parent stream or null
     */
    public function getParent(): ?Stream {
        return $this->parent;
    }
    
    /**
     * Check if this is a child stream
     * 
     * @return bool True if has parent
     */
    public function hasParent(): bool {
        return $this->parent !== null;
    }
    
    /**
     * Print a detailed report of stream processing
     * Useful for debugging
     */
    public function printReport(): void {
        echo "=== Stream Processing Report ===\n";
        echo "Stream ID: {$this->id}\n";
        echo "Iterations: {$this->iterations}\n";
        echo "Gates: " . count($this->gates) . "\n";
        echo "Result: " . ($this->result ?? '(none)') . "\n";
        echo "Rejected Events: " . count($this->rejectedEvents) . "\n";
        
        if ($this->loggingLevel >= LoggingLevel::DETAILED) {
            echo "\n--- Gate Pipeline ---\n";
            foreach ($this->gates as $i => $gate) {
                echo ($i + 1) . ". " . $gate->getShortName() . "\n";
            }
            
            if (!empty($this->rejectedEvents)) {
                echo "\n--- Rejected Events ---\n";
                foreach ($this->rejectedEvents as $event) {
                    echo "Input: {$event->data}\n";
                    echo "Rejected by: " . implode(', ', $event->rejectedBy) . "\n\n";
                }
            }
        }
        
        echo "================================\n";
    }
    
    /**
     * Get transformation history from all events
     * Only available with DETAILED logging
     * 
     * @return array Array of transformation records
     */
    public function getHistory(): array {
        $history = [];
        
        // Collect from rejected events
        foreach ($this->rejectedEvents as $event) {
            $history = array_merge($history, $event->getHistory());
        }
        
        return $history;
    }
}
