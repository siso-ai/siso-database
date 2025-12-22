<?php

require_once __DIR__ . '/../autoload.php';

use SISODatabase\Event;
use SISODatabase\Stream;
use SISODatabase\Database;
use SISODatabase\TableSchema;
use SISODatabase\ColumnDefinition;
use SISODatabase\Gates\CreateTableParseGate;
use SISODatabase\Gates\CreateTableExecuteGate;
use SISODatabase\Gates\InsertParseGate;
use SISODatabase\Gates\InsertExecuteGate;
use SISODatabase\Gates\ResultGate;

/**
 * INSERT All Phases Tests
 * 
 * Phase 1: Simple INSERT VALUES
 * Phase 2: INSERT with Column Names
 * Phase 3: Batch INSERT
 */
class Insert_AllPhasesTest {
    private int $passed = 0;
    private int $failed = 0;
    
    public function run(): void {
        echo "=== DML INSERT All Phases Tests ===\n\n";
        
        // Phase 1: Simple INSERT VALUES
        echo "=== PHASE 1: Simple INSERT VALUES ===\n";
        $this->test_simple_insert();
        $this->test_insert_with_types();
        $this->test_insert_with_null();
        $this->test_column_count_mismatch();
        $this->test_insert_nonexistent_table();
        echo "\n";
        
        // Phase 2: INSERT with Column Names
        echo "=== PHASE 2: INSERT with Column Names ===\n";
        $this->test_insert_with_column_names();
        $this->test_insert_partial_columns();
        $this->test_insert_columns_different_order();
        $this->test_insert_single_column();
        $this->test_invalid_column_name();
        $this->test_column_with_default();
        echo "\n";
        
        // Phase 3: Batch INSERT
        echo "=== PHASE 3: Batch INSERT ===\n";
        $this->test_batch_insert_simple();
        $this->test_batch_insert_with_columns();
        $this->test_batch_insert_multiple_rows();
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
    
    private function createTestTable(Database $db): void {
        $schema = new TableSchema('users');
        $schema->addColumnDefinition(new ColumnDefinition('id', 'INTEGER'));
        $schema->addColumnDefinition(new ColumnDefinition('name', 'TEXT'));
        $schema->addColumnDefinition(new ColumnDefinition('email', 'TEXT'));
        $db->createTable($schema);
    }
    
    private function createStream(Database $db): Stream {
        $stream = new Stream();
        $stream->registerGate(new InsertParseGate());
        $stream->registerGate(new InsertExecuteGate($db));
        $stream->registerGate(new ResultGate());
        return $stream;
    }
    
    // PHASE 1 TESTS
    
    private function test_simple_insert(): void {
        $db = new Database();
        $this->createTestTable($db);
        $stream = $this->createStream($db);
        
        $sql = "INSERT INTO users VALUES (1, 'Alice', 'alice@test.com')";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $table = $db->getTable('users');
        $this->assert($table->count() === 1, "One row inserted");
        
        $row = $table->getRow(0);
        $this->assert($row->get('id') == 1, "id value correct");
        $this->assert($row->get('name') === 'Alice', "name value correct");
        $this->assert($row->get('email') === 'alice@test.com', "email value correct");
    }
    
    private function test_insert_with_types(): void {
        $db = new Database();
        
        $schema = new TableSchema('products');
        $schema->addColumnDefinition(new ColumnDefinition('id', 'INTEGER'));
        $schema->addColumnDefinition(new ColumnDefinition('name', 'TEXT'));
        $schema->addColumnDefinition(new ColumnDefinition('price', 'REAL'));
        $db->createTable($schema);
        
        $stream = $this->createStream($db);
        
        $sql = "INSERT INTO products VALUES (100, 'Widget', 19.99)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $table = $db->getTable('products');
        $row = $table->getRow(0);
        
        $this->assert($row->get('id') === 100, "Integer parsed");
        $this->assert($row->get('name') === 'Widget', "String parsed");
        $this->assert($row->get('price') === 19.99, "Float parsed");
    }
    
    private function test_insert_with_null(): void {
        $db = new Database();
        $this->createTestTable($db);
        $stream = $this->createStream($db);
        
        $sql = "INSERT INTO users VALUES (2, 'Bob', NULL)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $table = $db->getTable('users');
        $row = $table->getRow(0);
        
        $this->assert($row->get('email') === null, "NULL value handled");
    }
    
    private function test_column_count_mismatch(): void {
        $db = new Database();
        $this->createTestTable($db);
        $stream = $this->createStream($db);
        
        $sql = "INSERT INTO users VALUES (3, 'Charlie')";  // Missing email
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'ERROR'), "Error for column count mismatch");
        $this->assert(str_contains($result, 'mismatch'), "Mentions mismatch");
    }
    
    private function test_insert_nonexistent_table(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "INSERT INTO nonexistent VALUES (1, 'test')";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'ERROR'), "Error for nonexistent table");
        $this->assert(str_contains($result, 'does not exist'), "Mentions table doesn't exist");
    }
    
    // PHASE 2 TESTS
    
    private function test_insert_with_column_names(): void {
        $db = new Database();
        $this->createTestTable($db);
        $stream = $this->createStream($db);
        
        $sql = "INSERT INTO users (id, name, email) VALUES (10, 'Dave', 'dave@test.com')";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $table = $db->getTable('users');
        $row = $table->getRow(0);
        
        $this->assert($row->get('id') == 10, "id from named column");
        $this->assert($row->get('name') === 'Dave', "name from named column");
        $this->assert($row->get('email') === 'dave@test.com', "email from named column");
    }
    
    private function test_insert_partial_columns(): void {
        $db = new Database();
        $this->createTestTable($db);
        $stream = $this->createStream($db);
        
        $sql = "INSERT INTO users (name, email) VALUES ('Eve', 'eve@test.com')";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $table = $db->getTable('users');
        $row = $table->getRow(0);
        
        $this->assert($row->get('id') === null, "Unspecified column is NULL");
        $this->assert($row->get('name') === 'Eve', "Specified column correct");
        $this->assert($row->get('email') === 'eve@test.com', "Specified column correct");
    }
    
    private function test_insert_columns_different_order(): void {
        $db = new Database();
        $this->createTestTable($db);
        $stream = $this->createStream($db);
        
        $sql = "INSERT INTO users (email, name, id) VALUES ('frank@test.com', 'Frank', 20)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $table = $db->getTable('users');
        $row = $table->getRow(0);
        
        $this->assert($row->get('id') == 20, "Column order doesn't matter");
        $this->assert($row->get('name') === 'Frank', "Values mapped correctly");
        $this->assert($row->get('email') === 'frank@test.com', "Values mapped correctly");
    }
    
    private function test_insert_single_column(): void {
        $db = new Database();
        $this->createTestTable($db);
        $stream = $this->createStream($db);
        
        $sql = "INSERT INTO users (name) VALUES ('Grace')";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $table = $db->getTable('users');
        $row = $table->getRow(0);
        
        $this->assert($row->get('name') === 'Grace', "Single column insert works");
        $this->assert($row->get('id') === null, "Other columns NULL");
        $this->assert($row->get('email') === null, "Other columns NULL");
    }
    
    private function test_invalid_column_name(): void {
        $db = new Database();
        $this->createTestTable($db);
        $stream = $this->createStream($db);
        
        $sql = "INSERT INTO users (id, invalid_col) VALUES (30, 'test')";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'ERROR'), "Error for invalid column");
        $this->assert(str_contains($result, 'does not exist'), "Mentions column doesn't exist");
    }
    
    private function test_column_with_default(): void {
        $db = new Database();
        
        $schema = new TableSchema('posts');
        $schema->addColumnDefinition(new ColumnDefinition('id', 'INTEGER'));
        $schema->addColumnDefinition(new ColumnDefinition('title', 'TEXT'));
        $statusCol = new ColumnDefinition('status', 'TEXT');
        $statusCol->setDefault('draft');
        $schema->addColumnDefinition($statusCol);
        $db->createTable($schema);
        
        $stream = $this->createStream($db);
        
        $sql = "INSERT INTO posts (id, title) VALUES (1, 'Hello')";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $table = $db->getTable('posts');
        $row = $table->getRow(0);
        
        $this->assert($row->get('status') === 'draft', "Default value used");
    }
    
    // PHASE 3 TESTS
    
    private function test_batch_insert_simple(): void {
        $db = new Database();
        $this->createTestTable($db);
        $stream = $this->createStream($db);
        
        $sql = "INSERT INTO users VALUES (1, 'Alice', 'alice@test.com'), (2, 'Bob', 'bob@test.com')";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $table = $db->getTable('users');
        $this->assert($table->count() === 2, "Two rows inserted");
        
        $row1 = $table->getRow(0);
        $this->assert($row1->get('name') === 'Alice', "First row correct");
        
        $row2 = $table->getRow(1);
        $this->assert($row2->get('name') === 'Bob', "Second row correct");
    }
    
    private function test_batch_insert_with_columns(): void {
        $db = new Database();
        $this->createTestTable($db);
        $stream = $this->createStream($db);
        
        $sql = "INSERT INTO users (name, email) VALUES ('Charlie', 'charlie@test.com'), ('Dave', 'dave@test.com')";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $table = $db->getTable('users');
        $this->assert($table->count() === 2, "Batch with columns works");
        
        $row1 = $table->getRow(0);
        $this->assert($row1->get('name') === 'Charlie', "First row correct");
        $this->assert($row1->get('id') === null, "Unspecified column NULL");
    }
    
    private function test_batch_insert_multiple_rows(): void {
        $db = new Database();
        $this->createTestTable($db);
        $stream = $this->createStream($db);
        
        $sql = "INSERT INTO users VALUES (1, 'A', 'a@test.com'), (2, 'B', 'b@test.com'), (3, 'C', 'c@test.com')";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $table = $db->getTable('users');
        $this->assert($table->count() === 3, "Three rows inserted");
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, '3 rows'), "Result mentions 3 rows");
    }
}

// Run tests
$test = new Insert_AllPhasesTest();
$test->run();
