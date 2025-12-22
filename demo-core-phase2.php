<?php

require_once 'autoload.php';

use SISODatabase\Event;
use SISODatabase\Gate;
use SISODatabase\Stream;
use SISODatabase\Gates\ResultGate;
use SISODatabase\Gates\ErrorGate;
use SISODatabase\LoggingLevel;

echo "=== SISO Core - Phase 2 Demo ===\n";
echo "Result and Error Handling\n\n";

// Simple calculator gates for demonstration

class AddGate extends Gate {
    public function matches(Event $event): bool {
        return preg_match('/^\d+\s*\+\s*\d+$/', $event->data);
    }
    
    public function transform(Event $event, Stream $stream): void {
        preg_match('/^(\d+)\s*\+\s*(\d+)$/', $event->data, $matches);
        $result = $matches[1] + $matches[2];
        $stream->emit(new Event("RESULT:{$result}", $stream->getId()));
    }
}

class MultiplyGate extends Gate {
    public function matches(Event $event): bool {
        return preg_match('/^\d+\s*\*\s*\d+$/', $event->data);
    }
    
    public function transform(Event $event, Stream $stream): void {
        preg_match('/^(\d+)\s*\*\s*(\d+)$/', $event->data, $matches);
        $result = $matches[1] * $matches[2];
        $stream->emit(new Event("RESULT:{$result}", $stream->getId()));
    }
}

// Test with valid inputs
echo "--- Valid Inputs ---\n\n";

$validInputs = [
    "5 + 3",
    "10 * 4",
    "100 + 25"
];

foreach ($validInputs as $input) {
    $stream = new Stream();
    $stream->registerGate(new AddGate());
    $stream->registerGate(new MultiplyGate());
    $stream->registerGate(new ResultGate());
    $stream->registerGate(new ErrorGate(false)); // Development mode
    
    echo "Input:  '{$input}'\n";
    $stream->emit(new Event($input, $stream->getId()));
    $stream->process();
    echo "Result: {$stream->getResult()}\n\n";
}

// Test with invalid inputs (development mode)
echo "--- Invalid Inputs (Development Mode) ---\n\n";

$invalidInputs = [
    "5 - 3",      // Subtraction not supported
    "abc + def",  // Not numbers
    "SELECT *"    // SQL but no gate for it
];

foreach ($invalidInputs as $input) {
    $stream = new Stream();
    $stream->registerGate(new AddGate());
    $stream->registerGate(new MultiplyGate());
    $stream->registerGate(new ResultGate());
    $stream->registerGate(new ErrorGate(false)); // Development mode
    
    echo "Input:  '{$input}'\n";
    $stream->emit(new Event($input, $stream->getId()));
    $stream->process();
    echo "Output:\n";
    echo $stream->getResult() . "\n\n";
    echo str_repeat("-", 60) . "\n\n";
}

// Test with invalid inputs (production mode)
echo "--- Invalid Inputs (Production Mode) ---\n\n";

foreach ($invalidInputs as $input) {
    $stream = new Stream();
    $stream->registerGate(new AddGate());
    $stream->registerGate(new MultiplyGate());
    $stream->registerGate(new ResultGate());
    $stream->registerGate(new ErrorGate(true)); // Production mode
    
    echo "Input:  '{$input}'\n";
    $stream->emit(new Event($input, $stream->getId()));
    $stream->process();
    echo "Result: {$stream->getResult()}\n\n";
}

// Demonstrate detailed logging
echo "--- With Detailed Logging ---\n\n";

$stream = new Stream();
$stream->setLoggingLevel(LoggingLevel::DETAILED);
$stream->registerGate(new AddGate());
$stream->registerGate(new ResultGate());

$stream->emit(new Event("15 + 27", $stream->getId()));
$stream->process();

$stream->printReport();

echo "\n=== Demo Complete ===\n";
echo "ResultGate and ErrorGate provide clean result and error handling!\n";
