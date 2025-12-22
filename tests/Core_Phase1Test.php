<?php

require_once __DIR__ . '/../autoload.php';

use SISODatabase\Event;
use SISODatabase\Gate;
use SISODatabase\Stream;
use SISODatabase\LoggingLevel;

/**
 * Phase 1 Tests - Basic Infrastructure
 * 
 * Tests the core SISO components: Event, Gate, Stream
 */
class Core_Phase1Test {
    private int $passed = 0;
    private int $failed = 0;
    
    public function run(): void {
        echo "=== SISO Core Phase 1 Tests ===\n\n";
        
        $this->test_event_creation();
        $this->test_event_rejection();
        $this->test_event_tracking();
        $this->test_stream_creation();
        $this->test_gate_registration();
        $this->test_event_emission();
        $this->test_basic_processing();
        $this->test_logging_levels();
        $this->test_max_iterations();
        
        echo "\n=== Test Summary ===\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "Total:  " . ($this->passed + $this->failed) . "\n";
        
        if ($this->failed === 0) {
            echo "\n✅ All tests passed!\n";
        } else {
            echo "\n❌ Some tests failed!\n";
        }
    }
    
    private function assert(bool $condition, string $message): void {
        if ($condition) {
            echo "✓ {$message}\n";
            $this->passed++;
        } else {
            echo "✗ {$message}\n";
            $this->failed++;
        }
    }
    
    private function test_event_creation(): void {
        echo "--- Event Creation ---\n";
        
        $event = new Event("SELECT * FROM users", "test-stream");
        
        $this->assert($event->data === "SELECT * FROM users", "Event stores data correctly");
        $this->assert($event->streamId === "test-stream", "Event stores stream ID");
        $this->assert(empty($event->rejectedBy), "Event starts with no rejections");
        $this->assert($event->gatesInRoom === 0, "Event starts with 0 gates");
        
        echo "\n";
    }
    
    private function test_event_rejection(): void {
        echo "--- Event Rejection ---\n";
        
        $event = new Event("test", "stream");
        $event->gatesInRoom = 3;
        
        $event->reject('Gate1');
        $this->assert(count($event->rejectedBy) === 1, "Rejection tracked");
        $this->assert(!$event->isRejectedByAll(), "Not rejected by all yet");
        
        $event->reject('Gate2');
        $event->reject('Gate3');
        $this->assert($event->isRejectedByAll(), "Rejected by all gates");
        
        // Test duplicate rejection
        $event->reject('Gate1');
        $this->assert(count($event->rejectedBy) === 3, "Duplicate rejection ignored");
        
        echo "\n";
    }
    
    private function test_event_tracking(): void {
        echo "--- Event Tracking ---\n";
        
        $event = new Event("input", "stream");
        
        // Minimal logging
        $event->track('Gate1', 'before', 'after', LoggingLevel::MINIMAL);
        $this->assert(count($event->transformedBy) === 1, "Gate tracked at MINIMAL level");
        $this->assert(empty($event->history), "No history at MINIMAL level");
        
        // Detailed logging
        $event->track('Gate2', 'step1', 'step2', LoggingLevel::DETAILED);
        $this->assert(count($event->transformedBy) === 2, "Second gate tracked");
        $this->assert(count($event->history) === 1, "History recorded at DETAILED level");
        
        $history = $event->getHistory();
        $this->assert($history[0]['gate'] === 'Gate2', "History contains gate name");
        $this->assert($history[0]['before'] === 'step1', "History contains before state");
        $this->assert($history[0]['after'] === 'step2', "History contains after state");
        
        echo "\n";
    }
    
    private function test_stream_creation(): void {
        echo "--- Stream Creation ---\n";
        
        $stream = new Stream();
        
        $this->assert($stream->getId() !== '', "Stream has ID");
        $this->assert($stream->getResult() === null, "Stream starts with no result");
        $this->assert(empty($stream->getGates()), "Stream starts with no gates");
        $this->assert(empty($stream->getRejectedEvents()), "Stream starts with no rejected events");
        
        echo "\n";
    }
    
    private function test_gate_registration(): void {
        echo "--- Gate Registration ---\n";
        
        $stream = new Stream();
        
        $gate1 = new class extends Gate {
            public function matches(Event $event): bool { return true; }
            public function transform(Event $event, Stream $stream): void {}
        };
        
        $gate2 = new class extends Gate {
            public function matches(Event $event): bool { return true; }
            public function transform(Event $event, Stream $stream): void {}
        };
        
        $stream->registerGate($gate1);
        $stream->registerGate($gate2);
        
        $gates = $stream->getGates();
        $this->assert(count($gates) === 2, "Two gates registered");
        $this->assert($gates[0] === $gate1, "Gates stored in order");
        $this->assert($gates[1] === $gate2, "Second gate stored");
        
        echo "\n";
    }
    
    private function test_event_emission(): void {
        echo "--- Event Emission ---\n";
        
        $stream = new Stream();
        $event = new Event("test", $stream->getId());
        
        $stream->emit($event);
        
        // We can't directly check the events queue (private)
        // But we can verify by processing with a gate
        $captured = null;
        $gate = new class($captured) extends Gate {
            private $captured;
            public function __construct(&$captured) {
                $this->captured = &$captured;
            }
            public function matches(Event $event): bool {
                $this->captured = $event->data;
                return false;
            }
            public function transform(Event $event, Stream $stream): void {}
        };
        
        $stream->registerGate($gate);
        $stream->process();
        
        $this->assert($captured === "test", "Event was emitted and processed");
        
        echo "\n";
    }
    
    private function test_basic_processing(): void {
        echo "--- Basic Processing ---\n";
        
        $stream = new Stream();
        
        // Create a gate that transforms "A" to "B"
        $gate = new class extends Gate {
            public function matches(Event $event): bool {
                return $event->data === "A";
            }
            public function transform(Event $event, Stream $stream): void {
                $stream->emit(new Event("B", $stream->getId()));
            }
        };
        
        $stream->registerGate($gate);
        $stream->emit(new Event("A", $stream->getId()));
        $stream->process();
        
        $this->assert($stream->getIterations() > 0, "Stream processed events");
        $this->assert($stream->getIterations() < 10, "Processing completed reasonably");
        
        echo "\n";
    }
    
    private function test_logging_levels(): void {
        echo "--- Logging Levels ---\n";
        
        $stream = new Stream();
        $stream->setLoggingLevel(LoggingLevel::DETAILED);
        
        $this->assert($stream->getLoggingLevel() === LoggingLevel::DETAILED, "Logging level set");
        
        $stream->setLoggingLevel(LoggingLevel::NONE);
        $this->assert($stream->getLoggingLevel() === LoggingLevel::NONE, "Logging level changed");
        
        echo "\n";
    }
    
    private function test_max_iterations(): void {
        echo "--- Max Iterations ---\n";
        
        $stream = new Stream();
        $stream->setMaxIterations(5);
        
        // Create a gate that infinitely loops
        $gate = new class extends Gate {
            public function matches(Event $event): bool {
                return true;
            }
            public function transform(Event $event, Stream $stream): void {
                // Emit same event back - infinite loop!
                $stream->emit(new Event($event->data, $stream->getId()));
            }
        };
        
        $stream->registerGate($gate);
        $stream->emit(new Event("loop", $stream->getId()));
        
        try {
            $stream->process();
            $this->assert(false, "Should have thrown exception for infinite loop");
        } catch (\RuntimeException $e) {
            $this->assert(
                str_contains($e->getMessage(), 'maximum iterations'),
                "Exception thrown for infinite loop"
            );
        }
        
        echo "\n";
    }
}

// Run tests
$test = new Core_Phase1Test();
$test->run();
