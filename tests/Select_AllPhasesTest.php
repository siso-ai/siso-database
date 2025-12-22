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
use SISODatabase\Gates\ProjectionGate;
use SISODatabase\Gates\OrderByGate;
use SISODatabase\Gates\LimitGate;
use SISODatabase\Gates\DistinctGate;
use SISODatabase\Gates\ResultSetGate;
use SISODatabase\Gates\ResultGate;

/**
 * SELECT All Phases Tests
 * 
 * Phase 1: SELECT * (Full Table Scan)
 * Phase 2: SELECT Specific Columns
 * Phase 3: ORDER BY
 * Phase 4: LIMIT and OFFSET
 * Phase 5: DISTINCT
 */
class Select_AllPhasesTest {
    private int $passed = 0;
    private int $failed = 0;
    
    public function run(): void {
        echo "=== DML SELECT All Phases Tests ===\n\n";
        
        // Phase 1: SELECT *
        echo "=== PHASE 1: SELECT * (Full Table Scan) ===\n";
        $this->test_select_star_all_rows();
        $this->test_select_star_empty_table();
        $this->test_select_nonexistent_table();
        echo "\n";
        
        // Phase 2: Specific Columns
        echo "=== PHASE 2: SELECT Specific Columns ===\n";
        $this->test_select_single_column();
        $this->test_select_multiple_columns();
        $this->test_select_column_order();
        $this->test_select_invalid_column();
        echo "\n";
        
        // Phase 3: ORDER BY
        echo "=== PHASE 3: ORDER BY ===\n";
        $this->test_order_by_asc();
        $this->test_order_by_desc();
        $this->test_order_by_text();
        $this->test_order_by_with_nulls();
        echo "\n";
        
        // Phase 4: LIMIT and OFFSET
        echo "=== PHASE 4: LIMIT and OFFSET ===\n";
        $this->test_limit_basic();
        $this->test_limit_with_offset();
        $this->test_limit_larger_than_results();
        echo "\n";
        
        // Phase 5: DISTINCT
        echo "=== PHASE 5: DISTINCT ===\n";
        $this->test_distinct_removes_duplicates();
        $this->test_distinct_with_projection();
        echo "\n";
        
        // Combined features
        echo "=== COMBINED FEATURES ===\n";
        $this->test_select_order_limit_combined();
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
        $schema->addColumnDefinition(new ColumnDefinition('email', 'TEXT'));
        $db->createTable($schema);
        
        $table = $db->getTable('users');
        $table->insert(new Row(['id' => 1, 'name' => 'Alice', 'age' => 28, 'email' => 'alice@test.com']));
        $table->insert(new Row(['id' => 2, 'name' => 'Bob', 'age' => 34, 'email' => 'bob@test.com']));
        $table->insert(new Row(['id' => 3, 'name' => 'Charlie', 'age' => 25, 'email' => 'charlie@test.com']));
        $table->insert(new Row(['id' => 4, 'name' => 'Diana', 'age' => 31, 'email' => null]));
        
        return $db;
    }
    
    private function createStream(Database $db): Stream {
        $stream = new Stream();
        $stream->registerGate(new SelectParseGate());
        $stream->registerGate(new TableScanGate($db));
        $stream->registerGate(new ProjectionGate());
        $stream->registerGate(new OrderByGate());
        $stream->registerGate(new LimitGate());
        $stream->registerGate(new DistinctGate());
        $stream->registerGate(new ResultSetGate());
        $stream->registerGate(new ResultGate());
        return $stream;
    }
    
    // PHASE 1 TESTS
    
    private function test_select_star_all_rows(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '4 rows returned'), "4 rows returned");
        $this->assert(str_contains($result, 'Alice'), "Contains Alice");
        $this->assert(str_contains($result, 'Bob'), "Contains Bob");
    }
    
    private function test_select_star_empty_table(): void {
        $db = new Database();
        $schema = new TableSchema('empty');
        $schema->addColumnDefinition(new ColumnDefinition('id', 'INTEGER'));
        $db->createTable($schema);
        
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM empty";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '0 rows returned'), "Empty table handled");
    }
    
    private function test_select_nonexistent_table(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM nonexistent";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'ERROR'), "Error for nonexistent table");
        $this->assert(str_contains($result, 'does not exist'), "Correct error message");
    }
    
    // PHASE 2 TESTS
    
    private function test_select_single_column(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT name FROM users";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '4 rows returned'), "4 rows");
        $this->assert(str_contains($result, 'name'), "name column header");
        $this->assert(str_contains($result, 'Alice'), "Contains data");
    }
    
    private function test_select_multiple_columns(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT name, age FROM users";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'name'), "name column");
        $this->assert(str_contains($result, 'age'), "age column");
        $this->assert(!str_contains($result, 'email'), "email NOT included");
    }
    
    private function test_select_column_order(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT age, name FROM users";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        // age should appear before name in output
        $agePos = strpos($result, 'age');
        $namePos = strpos($result, 'name');
        $this->assert($agePos < $namePos, "Column order preserved");
    }
    
    private function test_select_invalid_column(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT invalid FROM users";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'ERROR'), "Error for invalid column");
        $this->assert(str_contains($result, 'does not exist'), "Column doesn't exist");
    }
    
    // PHASE 3 TESTS
    
    private function test_order_by_asc(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users ORDER BY age ASC";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '4 rows'), "All rows returned");
        
        // Check order: Charlie (25), Alice (28), Diana (31), Bob (34)
        $charliePos = strpos($result, 'Charlie');
        $alicePos = strpos($result, 'Alice');
        $dianaPos = strpos($result, 'Diana');
        $bobPos = strpos($result, 'Bob');
        
        $this->assert($charliePos < $alicePos && $alicePos < $dianaPos && $dianaPos < $bobPos, 
                     "Ordered by age ASC");
    }
    
    private function test_order_by_desc(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users ORDER BY age DESC";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        
        // Check order: Bob (34), Diana (31), Alice (28), Charlie (25)
        $bobPos = strpos($result, 'Bob');
        $dianaPos = strpos($result, 'Diana');
        $alicePos = strpos($result, 'Alice');
        $charliePos = strpos($result, 'Charlie');
        
        $this->assert($bobPos < $dianaPos && $dianaPos < $alicePos && $alicePos < $charliePos,
                     "Ordered by age DESC");
    }
    
    private function test_order_by_text(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users ORDER BY name ASC";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        
        // Alphabetical: Alice, Bob, Charlie, Diana
        $alicePos = strpos($result, 'Alice');
        $bobPos = strpos($result, 'Bob');
        $charliePos = strpos($result, 'Charlie');
        
        $this->assert($alicePos < $bobPos && $bobPos < $charliePos, "Alphabetical order");
    }
    
    private function test_order_by_with_nulls(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users ORDER BY email ASC";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        // NULLs should be last
        $this->assert(str_contains($result, 'Diana'), "Diana with NULL email present");
    }
    
    // PHASE 4 TESTS
    
    private function test_limit_basic(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users LIMIT 2";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '2 rows returned'), "LIMIT 2 works");
    }
    
    private function test_limit_with_offset(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users LIMIT 2 OFFSET 1";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '2 rows returned'), "LIMIT OFFSET works");
        $this->assert(str_contains($result, 'Bob'), "Second row included");
        $this->assert(!str_contains($result, 'Alice'), "First row skipped");
    }
    
    private function test_limit_larger_than_results(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT * FROM users LIMIT 100";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '4 rows returned'), "Returns all available rows");
    }
    
    // PHASE 5 TESTS
    
    private function test_distinct_removes_duplicates(): void {
        $db = new Database();
        
        $schema = new TableSchema('colors');
        $schema->addColumnDefinition(new ColumnDefinition('id', 'INTEGER'));
        $schema->addColumnDefinition(new ColumnDefinition('color', 'TEXT'));
        $db->createTable($schema);
        
        $table = $db->getTable('colors');
        $table->insert(new Row(['id' => 1, 'color' => 'red']));
        $table->insert(new Row(['id' => 2, 'color' => 'blue']));
        $table->insert(new Row(['id' => 3, 'color' => 'red']));
        $table->insert(new Row(['id' => 4, 'color' => 'blue']));
        $table->insert(new Row(['id' => 5, 'color' => 'green']));
        
        $stream = $this->createStream($db);
        
        $sql = "SELECT DISTINCT color FROM colors";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '3 rows returned'), "DISTINCT works");
    }
    
    private function test_distinct_with_projection(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT DISTINCT name FROM users";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '4 rows'), "All distinct names");
    }
    
    // COMBINED TESTS
    
    private function test_select_order_limit_combined(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "SELECT name, age FROM users ORDER BY age DESC LIMIT 2";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '2 rows returned'), "Combined features work");
        $this->assert(str_contains($result, 'Bob'), "Oldest included");
        $this->assert(str_contains($result, 'Diana'), "Second oldest included");
        $this->assert(!str_contains($result, 'Charlie'), "Youngest excluded by LIMIT");
    }
}

// Run tests
$test = new Select_AllPhasesTest();
$test->run();
