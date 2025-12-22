<?php

require_once 'autoload.php';

use SISODatabase\Event;
use SISODatabase\Gate;
use SISODatabase\Stream;
use SISODatabase\LoggingLevel;

echo "=== SISO Core - Phase 1 Demo ===\n";
echo "Basic Infrastructure: Event, Gate, Stream\n\n";

// Simple demonstration gates

class UppercaseGate extends Gate {
    public function matches(Event $event): bool {
        // Match lowercase strings
        return preg_match('/^[a-z]+$/', $event->data);
    }
    
    public function transform(Event $event, Stream $stream): void {
        $upper = strtoupper($event->data);
        echo "  [UppercaseGate] '{$event->data}' → '{$upper}'\n";
        $stream->emit(new Event($upper, $stream->getId()));
    }
}

class AddExclamationGate extends Gate {
    public function matches(Event $event): bool {
        // Match uppercase strings without exclamation
        return preg_match('/^[A-Z]+$/', $event->data);
    }
    
    public function transform(Event $event, Stream $stream): void {
        $withExclamation = $event->data . "!";
        echo "  [AddExclamationGate] '{$event->data}' → '{$withExclamation}'\n";
        $stream->emit(new Event($withExclamation, $stream->getId()));
    }
}

class DoneGate extends Gate {
    public function matches(Event $event): bool {
        // Match strings ending with exclamation
        return str_ends_with($event->data, '!');
    }
    
    public function transform(Event $event, Stream $stream): void {
        echo "  [DoneGate] Processing complete: '{$event->data}'\n";
        // Don't emit anything - processing stops
    }
}

// Demo 1: Basic event flow
echo "--- Demo 1: Basic Event Flow ---\n";
echo "Transform 'hello' through the pipeline\n\n";

$stream1 = new Stream();
$stream1->registerGate(new UppercaseGate());
$stream1->registerGate(new AddExclamationGate());
$stream1->registerGate(new DoneGate());

echo "Input: 'hello'\n";
echo "Processing:\n";
$stream1->emit(new Event("hello", $stream1->getId()));
$stream1->process();

echo "\nIterations: {$stream1->getIterations()}\n";
echo "\n";

// Demo 2: Event rejection tracking
echo "--- Demo 2: Event Rejection Tracking ---\n";
echo "Process '12345' (no matching gate)\n\n";

$stream2 = new Stream();
$stream2->setLoggingLevel(LoggingLevel::DETAILED);
$stream2->registerGate(new UppercaseGate());
$stream2->registerGate(new AddExclamationGate());

echo "Input: '12345'\n";
$stream2->emit(new Event("12345", $stream2->getId()));
$stream2->process();

$rejected = $stream2->getRejectedEvents();
if (!empty($rejected)) {
    echo "\nRejected events:\n";
    foreach ($rejected as $event) {
        echo "  Data: '{$event->data}'\n";
        echo "  Rejected by: " . implode(', ', array_map(fn($g) => basename(str_replace('\\', '/', $g)), $event->rejectedBy)) . "\n";
    }
}
echo "\n";

// Demo 3: Transformation tracking
echo "--- Demo 3: Transformation Tracking ---\n";
echo "Track transformations with detailed logging\n\n";

class TrackedGate extends Gate {
    private string $name;
    private string $transform;
    
    public function __construct(string $name, string $transform) {
        $this->name = $name;
        $this->transform = $transform;
    }
    
    public function matches(Event $event): bool {
        return str_contains($event->data, 'X');
    }
    
    public function transform(Event $event, Stream $stream): void {
        $result = str_replace('X', $this->transform, $event->data);
        $stream->emit(new Event($result, $stream->getId()));
    }
    
    public function getName(): string {
        return $this->name;
    }
}

class FinalGate extends Gate {
    public function matches(Event $event): bool {
        return $event->data === 'DONE';
    }
    
    public function transform(Event $event, Stream $stream): void {
        // Stop processing
    }
}

$stream3 = new Stream();
$stream3->setLoggingLevel(LoggingLevel::DETAILED);
$stream3->registerGate(new TrackedGate("Step1", "A"));
$stream3->registerGate(new TrackedGate("Step2", "B"));
$stream3->registerGate(new TrackedGate("Step3", "DONE"));
$stream3->registerGate(new FinalGate());

echo "Input: 'X'\n";
$stream3->emit(new Event("X", $stream3->getId()));
$stream3->process();

echo "\nTransformation history:\n";
$history = $stream3->getHistory();
foreach ($history as $step) {
    echo "  {$step['gate']}: '{$step['before']}' → '{$step['after']}'\n";
}
echo "\n";

// Demo 4: Stream report
echo "--- Demo 4: Stream Report ---\n\n";

$stream4 = new Stream();
$stream4->setLoggingLevel(LoggingLevel::DETAILED);
$stream4->registerGate(new UppercaseGate());
$stream4->registerGate(new AddExclamationGate());
$stream4->registerGate(new DoneGate());

$stream4->emit(new Event("world", $stream4->getId()));
$stream4->process();

$stream4->printReport();

echo "\n=== Demo Complete ===\n";
echo "The SISO pattern successfully processes events through gates!\n";
