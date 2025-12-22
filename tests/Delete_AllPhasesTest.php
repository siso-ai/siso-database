<?php

require_once __DIR__ . '/../autoload.php';

use SISODatabase\Event;
use SISODatabase\Stream;
use SISODatabase\Database;
use SISODatabase\TableSchema;
use SISODatabase\ColumnDefinition;
use SISODatabase\Row;
use SISODatabase\Gates\DeleteParseGate;
use SISODatabase\Gates\DeleteExecuteGate;
use SISODatabase\Gates\ResultGate;

/**
 * DELETE All Phases Tests
 * 
 * Phase 3: Simple DELETE (all rows)
 * Phase 4: DELETE with WHERE clause
 */
class Delete_AllPhasesTest {
    private int $passed = 0;
    private int $failed = 0;
    
    public function run(): void {
        echo "=== DELETE All Phases Tests ===\n\n";
        
        // Phase 3: Simple DELETE
        echo "=== PHASE 3: Simple DELETE ===\n";
        $this->test_delete_all_rows();
        $this->test_delete_from_empty_table();
        echo "\n";
        
        // Phase 4: DELETE with WHERE
        echo "=== PHASE 4: DELETE with WHERE ===\n";
        $this->test_delete_with_where_equals();
        $this->test_delete_with_where_comparison();
        $this->test_delete_with_where_and();
        $this->test_delete_with_where_or();
        $this->test_delete_with_where_no_matches();
        $this->test_delete_multiple_rows();
        echo "\n";
        
        // Error Handling
        echo "=== ERROR HANDLING ===\n";
        $this->test_delete_nonexistent_table();
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
        $stream->registerGate(new DeleteParseGate());
        $stream->registerGate(new DeleteExecuteGate($db));
        $stream->registerGate(new ResultGate());
        return $stream;
    }
    
    // PHASE 3: Simple DELETE
    
    private function test_delete_all_rows(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "DELETE FROM users";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '4 rows deleted'), "DELETE all rows");
        
        $table = $db->getTable('users');
        $this->assert($table->count() === 0, "Table is empty");
    }
    
    private function test_delete_from_empty_table(): void {
        $db = new Database();
        $schema = new TableSchema('empty');
        $schema->addColumnDefinition(new ColumnDefinition('id', 'INTEGER'));
        $db->createTable($schema);
        
        $stream = $this->createStream($db);
        
        $sql = "DELETE FROM empty";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '0 rows deleted'), "DELETE from empty table");
    }
    
    // PHASE 4: DELETE with WHERE
    
    private function test_delete_with_where_equals(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "DELETE FROM users WHERE id = 2";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '1 row deleted'), "DELETE with WHERE equals");
        
        $table = $db->getTable('users');
        $this->assert($table->count() === 3, "One row deleted");
        
        $rows = $table->getAllRows();
        $this->assert($rows[0]->get('name') === 'Alice', "Alice remains");
        $this->assert($rows[1]->get('name') === 'Charlie', "Charlie remains");
    }
    
    private function test_delete_with_where_comparison(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "DELETE FROM users WHERE age < 30";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '2 rows deleted'), "DELETE with < comparison");
        
        $table = $db->getTable('users');
        $this->assert($table->count() === 2, "Two rows deleted");
        
        $rows = $table->getAllRows();
        $this->assert($rows[0]->get('age') >= 30, "Remaining row 1 age >= 30");
        $this->assert($rows[1]->get('age') >= 30, "Remaining row 2 age >= 30");
    }
    
    private function test_delete_with_where_and(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "DELETE FROM users WHERE city = 'NYC' AND age > 26";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '1 row deleted'), "DELETE with WHERE AND");
        
        $table = $db->getTable('users');
        $this->assert($table->count() === 3, "One row deleted");
        
        $rows = $table->getAllRows();
        $bobExists = false;
        $charlieExists = false;
        foreach ($rows as $row) {
            if ($row->get('name') === 'Bob') $bobExists = true;
            if ($row->get('name') === 'Charlie') $charlieExists = true;
        }
        $this->assert($bobExists, "Bob remains");
        $this->assert($charlieExists, "Charlie remains (NYC but age 25)");
    }
    
    private function test_delete_with_where_or(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "DELETE FROM users WHERE age < 26 OR age > 33";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '2 rows deleted'), "DELETE with WHERE OR");
        
        $table = $db->getTable('users');
        $this->assert($table->count() === 2, "Two rows deleted");
    }
    
    private function test_delete_with_where_no_matches(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "DELETE FROM users WHERE id = 999";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '0 rows deleted'), "DELETE with WHERE no matches");
        
        $table = $db->getTable('users');
        $this->assert($table->count() === 4, "All rows remain");
    }
    
    private function test_delete_multiple_rows(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "DELETE FROM users WHERE status = 'active'";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '3 rows deleted'), "DELETE multiple rows");
        
        $table = $db->getTable('users');
        $this->assert($table->count() === 1, "One row remains");
        $this->assert($table->getAllRows()[0]->get('name') === 'Charlie', "Charlie remains");
    }
    
    // ERROR HANDLING
    
    private function test_delete_nonexistent_table(): void {
        $db = $this->createTestDatabase();
        $stream = $this->createStream($db);
        
        $sql = "DELETE FROM nonexistent";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'ERROR'), "Error for nonexistent table");
        $this->assert(str_contains($result, 'does not exist'), "Error message mentions table doesn't exist");
    }
}

// Run tests
$test = new Delete_AllPhasesTest();
$test->run();
