<?php

namespace SISODatabase;

/**
 * Gate - Abstract base class for all transformation gates.
 * 
 * Gates are pure functions that:
 * 1. Match specific event patterns (matches method)
 * 2. Transform matched events into new events (transform method)
 * 3. Emit new events back into the stream
 * 
 * Gates should be stateless and have no side effects except emitting events.
 */
abstract class Gate {
    /**
     * Determine if this gate can process the given event
     * 
     * @param Event $event The event to check
     * @return bool True if this gate can handle the event
     */
    abstract public function matches(Event $event): bool;
    
    /**
     * Transform the event and emit result(s) to the stream
     * 
     * This method should:
     * 1. Extract data from the event
     * 2. Perform the transformation
     * 3. Create new event(s) with the result
     * 4. Emit new event(s) to the stream
     * 
     * @param Event $event The event to transform
     * @param Stream $stream The stream to emit results to
     */
    abstract public function transform(Event $event, Stream $stream): void;
    
    /**
     * Get the name of this gate (class name by default)
     * 
     * @return string The gate name
     */
    public function getName(): string {
        return static::class;
    }
    
    /**
     * Get a short name for this gate (without namespace)
     * Useful for logging and debugging
     * 
     * @return string Short gate name
     */
    public function getShortName(): string {
        $parts = explode('\\', static::class);
        return end($parts);
    }
}
