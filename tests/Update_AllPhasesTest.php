<?php

require_once __DIR__ . '/../autoload.php';

use SISODatabase\Event;
use SISODatabase\Stream;
use SISODatabase\Database;
use SISODatabase\TableSchema;
use SISODatabase\ColumnDefinition;
use SISODatabase\Row;
use SISODatabase\Gates\CreateTableParseGate;
use SISODatabase\Gates\CreateTableExecuteGate;
use SISODatabase\Gates\InsertParseGate;
use SISODatabase\Gates\InsertExecuteGate;
use SISODatabase\Gates\UpdateParseGate;
use SISODatabase\Gates\UpdateExecuteGate;
use SISODatabase\Gates\ResultGate;

/**
 * UPDATE All Phases Tests
 * 
 * Phase 1: Simple UPDATE (all rows)
 * Phase 2: UPDATE with WHERE clause
 */
class Update_AllPhasesTest {
    private int $passed = 0;
    private int $failed = 0;
    
    public function run(): void {
        echo "=== UPDATE All Phases Tests ===\n\n";
        
        // Phase 1: Simple UPDATE
        echo "=== PHASE 1: Simple UPDATE ===\n";
        $this->test_update_single_column();
        $this->test_update_multiple_columns();
        $this->test_update_to_null();
        $this->test_update_all_rows();
        $this->test_update_preserves_other_columns();
        echo "\n";
        
        // Phase 2: UPDATE with WHERE
        echo "=== PHASE 2: UPDATE with WHERE ===\n";
        $this->test_update_with_where_equals();
        $this->test_update_with_where_comparison();
        $this->test_update_with_where_and();
        $this->test_update_with_where_or();
        $this->test_update_with_where_no_matches();
        echo "\n";
        
        // Error Handling
        echo "=== ERROR HANDLING ===\n";
        $this->test_update_nonexistent_table();
        $this->test_update_nonexistent_column();
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
        $schema->addColumnDefinition(new ColumnDefinition('status', 'TEXT'));
        $db->createTable($schema);
        
        $table = $db->getTable('users');
        $table->insert(new Row(['id' => 1, 'name' => 'Alice', 'age' => 28, 'city' => 'NYC', 'status' => 'active']));
        $table->insert(new Row(['id' => 2, 'name' => 'Bob', 'age' => 34, 'city' => 'LA', 'status' => 'active']));
        $table->insert(new Row(['id' => 3, 'name' => 'Charlie', 'age' => 25, 'city' => 'NYC', 'status' => 'inactive']));
        $table->insert(new Row(['id' => 4, 'name' => 'Diana', 'age' => 31, 'city' => 'SF', 'status' => 'active']));
        
        return $db;
    }
    
    private function createStream(Database $db): Stream {
        $stream = new Stream();
        $stream->registerGate(new UpdateParseGate());
        $stream->registerGate(new UpdateExecuteGate($db));
        $stream->registerGate(new ResultGate());
        return $stream;
    }
    
    // PHASE 1: Simple UPDATE
    
    private function test_update_single_column(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "UPDATE users SET age = 30";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '4 rows updated'), "UPDATE all rows with single column");
        
        $table = $db->getTable('users');
        $rows = $table->getAllRows();
        $this->assert($rows[0]->get('age') === 30, "First row age updated");
        $this->assert($rows[1]->get('age') === 30, "Second row age updated");
    }
    
    private function test_update_multiple_columns(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "UPDATE users SET age = 99, status = 'updated'";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '4 rows updated'), "UPDATE multiple columns");
        
        $table = $db->getTable('users');
        $row = $table->getAllRows()[0];
        $this->assert($row->get('age') === 99, "Age updated");
        $this->assert($row->get('status') === 'updated', "Status updated");
    }
    
    private function test_update_to_null(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "UPDATE users SET status = NULL";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $table = $db->getTable('users');
        $row = $table->getAllRows()[0];
        $this->assert($row->get('status') === null, "Can update to NULL");
    }
    
    private function test_update_all_rows(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "UPDATE users SET city = 'Boston'";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $table = $db->getTable('users');
        $rows = $table->getAllRows();
        $allBoston = true;
        foreach ($rows as $row) {
            if ($row->get('city') !== 'Boston') {
                $allBoston = false;
                break;
            }
        }
        $this->assert($allBoston, "All rows updated when no WHERE clause");
    }
    
    private function test_update_preserves_other_columns(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "UPDATE users SET age = 50";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $table = $db->getTable('users');
        $rows = $table->getAllRows();
        $this->assert($rows[0]->get('name') === 'Alice', "Name preserved");
        $this->assert($rows[1]->get('city') === 'LA', "City preserved");
    }
    
    // PHASE 2: UPDATE with WHERE
    
    private function test_update_with_where_equals(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "UPDATE users SET age = 35 WHERE id = 1";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '1 row updated'), "UPDATE with WHERE equals");
        
        $table = $db->getTable('users');
        $rows = $table->getAllRows();
        $this->assert($rows[0]->get('age') === 35, "First row updated");
        $this->assert($rows[1]->get('age') === 34, "Second row not updated");
    }
    
    private function test_update_with_where_comparison(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "UPDATE users SET status = 'senior' WHERE age > 30";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '2 rows updated'), "UPDATE with > comparison");
        
        $table = $db->getTable('users');
        $rows = $table->getAllRows();
        $this->assert($rows[1]->get('status') === 'senior', "Bob updated (34)");
        $this->assert($rows[3]->get('status') === 'senior', "Diana updated (31)");
        $this->assert($rows[0]->get('status') === 'active', "Alice not updated (28)");
    }
    
    private function test_update_with_where_and(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "UPDATE users SET status = 'nyc_senior' WHERE city = 'NYC' AND age > 26";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '1 row updated'), "UPDATE with WHERE AND");
        
        $table = $db->getTable('users');
        $rows = $table->getAllRows();
        $this->assert($rows[0]->get('status') === 'nyc_senior', "Alice updated (NYC, 28)");
        $this->assert($rows[2]->get('status') === 'inactive', "Charlie not updated (NYC, 25)");
    }
    
    private function test_update_with_where_or(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "UPDATE users SET status = 'special' WHERE age < 26 OR age > 33";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '2 rows updated'), "UPDATE with WHERE OR");
        
        $table = $db->getTable('users');
        $rows = $table->getAllRows();
        $this->assert($rows[1]->get('status') === 'special', "Bob updated (34)");
        $this->assert($rows[2]->get('status') === 'special', "Charlie updated (25)");
    }
    
    private function test_update_with_where_no_matches(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "UPDATE users SET age = 100 WHERE id = 999";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '0 rows updated'), "UPDATE with WHERE no matches");
    }
    
    // ERROR HANDLING
    
    private function test_update_nonexistent_table(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "UPDATE nonexistent SET age = 30";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'ERROR'), "Error for nonexistent table");
        $this->assert(str_contains($result, 'does not exist'), "Error message mentions table doesn't exist");
    }
    
    private function test_update_nonexistent_column(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "UPDATE users SET invalid_column = 30";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'ERROR'), "Error for nonexistent column");
        $this->assert(str_contains($result, 'does not exist'), "Error message mentions column doesn't exist");
    }
}

// Run tests
$test = new Update_AllPhasesTest();
$test->run();
