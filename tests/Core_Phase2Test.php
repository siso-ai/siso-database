<?php

require_once __DIR__ . '/../autoload.php';

use SISODatabase\Event;
use SISODatabase\Gate;
use SISODatabase\Stream;
use SISODatabase\Gates\ResultGate;
use SISODatabase\Gates\ErrorGate;
use SISODatabase\LoggingLevel;

/**
 * Phase 2 Tests - Result and Error Handling
 * 
 * Tests ResultGate and ErrorGate functionality
 */
class Core_Phase2Test {
    private int $passed = 0;
    private int $failed = 0;
    
    public function run(): void {
        echo "=== SISO Core Phase 2 Tests ===\n\n";
        
        $this->test_result_gate_matches();
        $this->test_result_gate_extraction();
        $this->test_result_gate_stops_processing();
        $this->test_error_gate_development_mode();
        $this->test_error_gate_production_mode();
        $this->test_error_gate_matches_rejected();
        $this->test_complete_pipeline();
        $this->test_error_gate_order_matters();
        $this->test_multiple_results_last_wins();
        
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
    
    private function test_result_gate_matches(): void {
        echo "--- ResultGate Matching ---\n";
        
        $gate = new ResultGate();
        
        $resultEvent = new Event("RESULT:42", "stream");
        $normalEvent = new Event("SELECT * FROM users", "stream");
        
        $this->assert($gate->matches($resultEvent), "Matches RESULT: prefix");
        $this->assert(!$gate->matches($normalEvent), "Doesn't match normal events");
        
        echo "\n";
    }
    
    private function test_result_gate_extraction(): void {
        echo "--- ResultGate Extraction ---\n";
        
        $stream = new Stream();
        $gate = new ResultGate();
        
        $stream->registerGate($gate);
        $stream->emit(new Event("RESULT:Hello World", $stream->getId()));
        $stream->process();
        
        $this->assert($stream->getResult() === "Hello World", "Result extracted correctly");
        
        echo "\n";
    }
    
    private function test_result_gate_stops_processing(): void {
        echo "--- ResultGate Stops Processing ---\n";
        
        $stream = new Stream();
        
        $stream->registerGate(new ResultGate());
        $stream->emit(new Event("RESULT:done", $stream->getId()));
        $stream->process();
        
        // Should only take 1 iteration
        $this->assert($stream->getIterations() === 1, "Processing stopped after result");
        
        echo "\n";
    }
    
    private function test_error_gate_development_mode(): void {
        echo "--- ErrorGate Development Mode ---\n";
        
        $stream = new Stream();
        $stream->registerGate(new ResultGate());
        $stream->registerGate(new ErrorGate(false)); // Development mode
        
        $event = new Event("INVALID SQL", $stream->getId());
        $event->gatesInRoom = 2;
        $event->reject('SISODatabase\\Gates\\ResultGate');
        $event->reject('SISODatabase\\Gates\\ErrorGate');
        
        $stream->emit($event);
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'ERROR:'), "Error message returned");
        $this->assert(str_contains($result, 'INVALID SQL'), "Contains original input");
        $this->assert(str_contains($result, 'EVENT PROCESSING ERROR'), "Detailed error shown");
        
        echo "\n";
    }
    
    private function test_error_gate_production_mode(): void {
        echo "--- ErrorGate Production Mode ---\n";
        
        $stream = new Stream();
        $stream->registerGate(new ResultGate());
        $stream->registerGate(new ErrorGate(true)); // Production mode
        
        $event = new Event("INVALID SQL", $stream->getId());
        $event->gatesInRoom = 2;
        $event->reject('SISODatabase\\Gates\\ResultGate');
        $event->reject('SISODatabase\\Gates\\ErrorGate');
        
        $stream->emit($event);
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert($result === "ERROR: Invalid SQL syntax", "Simple error in production");
        $this->assert(!str_contains($result, 'EVENT PROCESSING ERROR'), "No detailed error");
        
        echo "\n";
    }
    
    private function test_error_gate_matches_rejected(): void {
        echo "--- ErrorGate Matches Only Rejected ---\n";
        
        $errorGate = new ErrorGate();
        
        $rejectedEvent = new Event("test", "stream");
        $rejectedEvent->gatesInRoom = 2;
        $rejectedEvent->reject('Gate1');
        $rejectedEvent->reject('Gate2');
        
        $normalEvent = new Event("test", "stream");
        $normalEvent->gatesInRoom = 2;
        $normalEvent->reject('Gate1');
        
        $this->assert($errorGate->matches($rejectedEvent), "Matches fully rejected event");
        $this->assert(!$errorGate->matches($normalEvent), "Doesn't match partially rejected");
        
        echo "\n";
    }
    
    private function test_complete_pipeline(): void {
        echo "--- Complete Pipeline ---\n";
        
        $stream = new Stream();
        
        // Simple gate that processes valid input
        $processGate = new class extends Gate {
            public function matches(Event $event): bool {
                return $event->data === "valid";
            }
            public function transform(Event $event, Stream $stream): void {
                $stream->emit(new Event("RESULT:processed", $stream->getId()));
            }
        };
        
        $stream->registerGate($processGate);
        $stream->registerGate(new ResultGate());
        $stream->registerGate(new ErrorGate(true));
        
        // Test valid input
        $stream->emit(new Event("valid", $stream->getId()));
        $stream->process();
        $this->assert($stream->getResult() === "processed", "Valid input processed correctly");
        
        echo "\n";
    }
    
    private function test_error_gate_order_matters(): void {
        echo "--- ErrorGate Order Matters ---\n";
        
        $stream = new Stream();
        
        // ErrorGate should be last
        $stream->registerGate(new ResultGate());
        $stream->registerGate(new ErrorGate(true));
        
        $event = new Event("unmatched", $stream->getId());
        $event->gatesInRoom = 2;
        $event->reject('SISODatabase\\Gates\\ResultGate');
        $event->reject('SISODatabase\\Gates\\ErrorGate');
        
        $stream->emit($event);
        $stream->process();
        
        $this->assert(str_contains($stream->getResult(), "ERROR:"), "Error handled when last");
        
        echo "\n";
    }
    
    private function test_multiple_results_last_wins(): void {
        echo "--- Multiple Results - Last Wins ---\n";
        
        $stream = new Stream();
        $stream->registerGate(new ResultGate());
        
        $stream->emit(new Event("RESULT:first", $stream->getId()));
        $stream->emit(new Event("RESULT:second", $stream->getId()));
        $stream->process();
        
        // Last result should win
        $this->assert($stream->getResult() === "second", "Last result wins");
        
        echo "\n";
    }
}

// Run tests
$test = new Core_Phase2Test();
$test->run();
