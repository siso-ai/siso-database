<?php

require_once __DIR__ . '/../autoload.php';

use SISODatabase\Event;
use SISODatabase\Stream;
use SISODatabase\Database;
use SISODatabase\TableSchema;
use SISODatabase\ColumnDefinition;
use SISODatabase\Row;
use SISODatabase\Gates\SelectParseGate;
use SISODatabase\Gates\TableScanGate;
use SISODatabase\Gates\FilterGate;
use SISODatabase\Gates\ProjectionGate;
use SISODatabase\Gates\OrderByGate;
use SISODatabase\Gates\LimitGate;
use SISODatabase\Gates\DistinctGate;
use SISODatabase\Gates\ResultSetGate;
use SISODatabase\Gates\ResultGate;

/**
 * WHERE Clause All Phases Tests
 * 
 * Phase 1: Simple Comparisons (=, !=, <, >, <=, >=)
 * Phase 2: Compound Conditions (AND)
 * Phase 3: OR and Complex Logic
 * Phase 4: Special Operators (IN, LIKE, BETWEEN, IS NULL, IS NOT NULL)
 */
class Where_AllPhasesTest {
    private int $passed = 0;
    private int $failed = 0;
    
    public function run(): void {
        echo "=== WHERE Clause All Phases Tests ===\n\n";
        
        // Phase 1: Simple Comparisons
        echo "=== PHASE 1: Simple Comparisons ===\n";
        $this->test_where_equals();
        $this->test_where_not_equals();
        $this->test_where_less_than();
        $this->test_where_greater_than();
        $this->test_where_less_than_or_equal();
        $this->test_where_greater_than_or_equal();
        $this->test_where_string_comparison();
        echo "\n";
        
        // Phase 2: AND Conditions
        echo "=== PHASE 2: AND Conditions ===\n";
        $this->test_where_and_simple();
        $this->test_where_and_multiple_conditions();
        $this->test_where_and_no_matches();
        echo "\n";
        
        // Phase 3: OR and Complex Logic
        echo "=== PHASE 3: OR and Complex Logic ===\n";
        $this->test_where_or_simple();
        $this->test_where_mixed_and_or();
        echo "\n";
        
        // Phase 4: Special Operators
        echo "=== PHASE 4: Special Operators ===\n";
        $this->test_where_in_operator();
        $this->test_where_like_wildcard_start();
        $this->test_where_like_wildcard_end();
        $this->test_where_between();
        $this->test_where_is_null();
        $this->test_where_is_not_null();
        echo "\n";
        
        // Integration Tests
        echo "=== INTEGRATION TESTS ===\n";
        $this->test_where_with_order_by();
        $this->test_where_with_limit();
        $this->test_where_with_projection();
        echo "\n";
        
        echo "=== Test Summary ===\n";
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
    
    private function createTestDatabase(): Database {
        $db = new Database();
        
        $schema = new TableSchema('users');
        $schema->addColumnDefinition(new ColumnDefinition('id', 'INTEGER'));
        $schema->addColumnDefinition(new ColumnDefinition('name', 'TEXT'));
        $schema->addColumnDefinition(new ColumnDefinition('age', 'INTEGER'));
        $schema->addColumnDefinition(new ColumnDefinition('city', 'TEXT'));
        $schema->addColumnDefinition(new ColumnDefinition('email', 'TEXT'));
        $db->createTable($schema);
        
        $table = $db->getTable('users');
        $table->insert(new Row(['id' => 1, 'name' => 'Alice', 'age' => 28, 'city' => 'NYC', 'email' => 'alice@test.com']));
        $table->insert(new Row(['id' => 2, 'name' => 'Bob', 'age' => 34, 'city' => 'LA', 'email' => 'bob@test.com']));
        $table->insert(new Row(['id' => 3, 'name' => 'Charlie', 'age' => 25, 'city' => 'NYC', 'email' => 'charlie@test.com']));
        $table->insert(new Row(['id' => 4, 'name' => 'Diana', 'age' => 31, 'city' => 'NYC', 'email' => null]));
        $table->insert(new Row(['id' => 5, 'name' => 'Eve', 'age' => 28, 'city' => 'LA', 'email' => 'eve@test.com']));
        
        return $db;
    }
    
    private function createStream(Database $db): Stream {
        $stream = new Stream();
        $stream->registerGate(new SelectParseGate());
        $stream->registerGate(new TableScanGate($db));
        $stream->registerGate(new FilterGate());
        $stream->registerGate(new ProjectionGate());
        $stream->registerGate(new OrderByGate());
        $stream->registerGate(new LimitGate());
        $stream->registerGate(new DistinctGate());
        $stream->registerGate(new ResultSetGate());
        $stream->registerGate(new ResultGate());
        return $stream;
    }
    
    // PHASE 1 TESTS - Simple Comparisons
    
    private function test_where_equals(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE age = 28";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '2 rows returned'), "WHERE age = 28 returns 2 rows");
        $this->assert(str_contains($result, 'Alice'), "Contains Alice");
        $this->assert(str_contains($result, 'Eve'), "Contains Eve");
        $this->assert(!str_contains($result, 'Bob'), "Does not contain Bob");
    }
    
    private function test_where_not_equals(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE city != 'NYC'";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '2 rows returned'), "WHERE city != NYC returns 2 rows");
        $this->assert(str_contains($result, 'Bob'), "Contains Bob");
        $this->assert(str_contains($result, 'Eve'), "Contains Eve");
    }
    
    private function test_where_less_than(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE age < 30";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '3 rows returned'), "WHERE age < 30 returns 3 rows");
        $this->assert(str_contains($result, 'Charlie'), "Contains Charlie (25)");
    }
    
    private function test_where_greater_than(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE age > 30";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '2 rows returned'), "WHERE age > 30 returns 2 rows");
        $this->assert(str_contains($result, 'Bob'), "Contains Bob (34)");
        $this->assert(str_contains($result, 'Diana'), "Contains Diana (31)");
    }
    
    private function test_where_less_than_or_equal(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE age <= 28";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '3 rows returned'), "WHERE age <= 28 returns 3 rows");
    }
    
    private function test_where_greater_than_or_equal(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE age >= 31";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '2 rows returned'), "WHERE age >= 31 returns 2 rows");
    }
    
    private function test_where_string_comparison(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE city = 'NYC'";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '3 rows returned'), "WHERE city = NYC returns 3 rows");
    }
    
    // PHASE 2 TESTS - AND Conditions
    
    private function test_where_and_simple(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE age > 25 AND city = 'NYC'";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '2 rows returned'), "AND condition returns 2 rows");
        $this->assert(str_contains($result, 'Alice'), "Contains Alice");
        $this->assert(str_contains($result, 'Diana'), "Contains Diana");
        $this->assert(!str_contains($result, 'Charlie'), "Charlie excluded (age too low)");
    }
    
    private function test_where_and_multiple_conditions(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE age >= 25 AND age <= 30 AND city = 'NYC'";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '2 rows returned'), "Multiple AND conditions work");
    }
    
    private function test_where_and_no_matches(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE age < 26 AND city = 'LA'";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '0 rows returned'), "AND with no matches");
    }
    
    // PHASE 3 TESTS - OR and Complex Logic
    
    private function test_where_or_simple(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE age < 26 OR age > 33";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '2 rows returned'), "OR condition works");
        $this->assert(str_contains($result, 'Charlie'), "Contains Charlie (25)");
        $this->assert(str_contains($result, 'Bob'), "Contains Bob (34)");
    }
    
    private function test_where_mixed_and_or(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        // OR has lower precedence, so this is: (age < 26) OR (age > 30 AND city = 'NYC')
        $sql = "SELECT * FROM users WHERE age < 26 OR age > 30 AND city = 'NYC'";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'Charlie'), "Mixed AND/OR works");
        $this->assert(str_contains($result, 'Diana'), "Diana included (31, NYC)");
    }
    
    // PHASE 4 TESTS - Special Operators
    
    private function test_where_in_operator(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE id IN (1, 3, 5)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '3 rows returned'), "IN operator works");
        $this->assert(str_contains($result, 'Alice'), "Contains Alice");
        $this->assert(str_contains($result, 'Charlie'), "Contains Charlie");
        $this->assert(str_contains($result, 'Eve'), "Contains Eve");
    }
    
    private function test_where_like_wildcard_start(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE name LIKE 'A%'";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '1 row returned'), "LIKE with % at end works");
        $this->assert(str_contains($result, 'Alice'), "Contains Alice");
    }
    
    private function test_where_like_wildcard_end(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE email LIKE '%@test.com'";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '4 rows returned'), "LIKE with % at start works");
    }
    
    private function test_where_between(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE age BETWEEN 28 AND 32";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '3 rows returned'), "BETWEEN operator works");
        $this->assert(str_contains($result, 'Alice'), "Contains Alice (28)");
        $this->assert(str_contains($result, 'Eve'), "Contains Eve (28)");
        $this->assert(str_contains($result, 'Diana'), "Contains Diana (31)");
    }
    
    private function test_where_is_null(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE email IS NULL";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '1 row returned'), "IS NULL works");
        $this->assert(str_contains($result, 'Diana'), "Contains Diana");
    }
    
    private function test_where_is_not_null(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE email IS NOT NULL";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '4 rows returned'), "IS NOT NULL works");
        $this->assert(!str_contains($result, 'Diana'), "Diana excluded");
    }
    
    // INTEGRATION TESTS
    
    private function test_where_with_order_by(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE city = 'NYC' ORDER BY age ASC";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '3 rows returned'), "WHERE with ORDER BY works");
        
        // Check order: Charlie (25), Alice (28), Diana (31)
        $charliePos = strpos($result, 'Charlie');
        $alicePos = strpos($result, 'Alice');
        $dianaPos = strpos($result, 'Diana');
        
        $this->assert($charliePos < $alicePos && $alicePos < $dianaPos, "Ordering correct");
    }
    
    private function test_where_with_limit(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users WHERE city = 'NYC' LIMIT 2";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '2 rows returned'), "WHERE with LIMIT works");
    }
    
    private function test_where_with_projection(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT name, age FROM users WHERE age > 30";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '2 rows returned'), "WHERE with projection works");
        $this->assert(str_contains($result, 'name'), "name column present");
        $this->assert(str_contains($result, 'age'), "age column present");
        $this->assert(!str_contains($result, 'city'), "city column not present");
    }
}

// Run tests
$test = new Where_AllPhasesTest();
$test->run();
