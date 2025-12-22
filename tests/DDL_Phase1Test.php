<?php

require_once __DIR__ . '/../autoload.php';

use SISODatabase\Event;
use SISODatabase\Stream;
use SISODatabase\Database;
use SISODatabase\Gates\CreateTableParseGate;
use SISODatabase\Gates\CreateTableExecuteGate;
use SISODatabase\Gates\ResultGate;
use SISODatabase\Gates\ErrorGate;

/**
 * DDL Phase 1 Tests - Basic CREATE TABLE
 * 
 * Tests parsing and creating simple tables with column names.
 */
class DDL_Phase1Test {
    private int $passed = 0;
    private int $failed = 0;
    
    public function run(): void {
        echo "=== DDL Phase 1 Tests ===\n";
        echo "Basic CREATE TABLE\n\n";
        
        $this->test_simple_create_table();
        $this->test_multiple_columns();
        $this->test_table_already_exists();
        $this->test_if_not_exists_new_table();
        $this->test_if_not_exists_existing_table();
        $this->test_invalid_syntax();
        $this->test_empty_column_list();
        
        echo "\n=== Test Summary ===\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "Total:  " . ($this->passed + $this->failed) . "\n";
        
        if ($this->failed === 0) {
            echo "\n✅ All Phase 1 tests passed!\n";
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
    
    private function test_simple_create_table(): void {
        echo "--- Simple CREATE TABLE ---\n";
        
        $db = new Database();
        $stream = new Stream();
        $stream->registerGate(new CreateTableParseGate());
        $stream->registerGate(new CreateTableExecuteGate($db));
        $stream->registerGate(new ResultGate());
        
        $sql = "CREATE TABLE users (id, name, email)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $this->assert($db->hasTable('users'), "Table created");
        $this->assert($stream->getResult() === "Table 'users' created", "Success message");
        
        $schema = $db->getTableSchema('users');
        $this->assert($schema->getColumnCount() === 3, "Three columns");
        $this->assert($schema->hasColumn('id'), "Has 'id' column");
        $this->assert($schema->hasColumn('name'), "Has 'name' column");
        $this->assert($schema->hasColumn('email'), "Has 'email' column");
        
        echo "\n";
    }
    
    private function test_multiple_columns(): void {
        echo "--- Multiple Columns ---\n";
        
        $db = new Database();
        $stream = new Stream();
        $stream->registerGate(new CreateTableParseGate());
        $stream->registerGate(new CreateTableExecuteGate($db));
        $stream->registerGate(new ResultGate());
        
        $sql = "CREATE TABLE products (sku, name, price, description, category)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $schema = $db->getTableSchema('products');
        $this->assert($schema->getColumnCount() === 5, "Five columns created");
        
        $names = $schema->getColumnNames();
        $this->assert(in_array('sku', $names), "Has 'sku'");
        $this->assert(in_array('name', $names), "Has 'name'");
        $this->assert(in_array('price', $names), "Has 'price'");
        $this->assert(in_array('description', $names), "Has 'description'");
        $this->assert(in_array('category', $names), "Has 'category'");
        
        echo "\n";
    }
    
    private function test_table_already_exists(): void {
        echo "--- Table Already Exists ---\n";
        
        $db = new Database();
        $stream = new Stream();
        $stream->registerGate(new CreateTableParseGate());
        $stream->registerGate(new CreateTableExecuteGate($db));
        $stream->registerGate(new ResultGate());
        
        // Create first time
        $sql = "CREATE TABLE users (id, name)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        // Try to create again
        $stream2 = new Stream();
        $stream2->registerGate(new CreateTableParseGate());
        $stream2->registerGate(new CreateTableExecuteGate($db));
        $stream2->registerGate(new ResultGate());
        
        $stream2->emit(new Event($sql, $stream2->getId()));
        $stream2->process();
        
        $result = $stream2->getResult();
        $this->assert(str_contains($result, 'ERROR'), "Error returned");
        $this->assert(str_contains($result, 'already exists'), "Correct error message");
        
        echo "\n";
    }
    
    private function test_if_not_exists_new_table(): void {
        echo "--- IF NOT EXISTS (New Table) ---\n";
        
        $db = new Database();
        $stream = new Stream();
        $stream->registerGate(new CreateTableParseGate());
        $stream->registerGate(new CreateTableExecuteGate($db));
        $stream->registerGate(new ResultGate());
        
        $sql = "CREATE TABLE IF NOT EXISTS users (id, name)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $this->assert($db->hasTable('users'), "Table created");
        $this->assert($stream->getResult() === "Table 'users' created", "Success message");
        
        echo "\n";
    }
    
    private function test_if_not_exists_existing_table(): void {
        echo "--- IF NOT EXISTS (Existing Table) ---\n";
        
        $db = new Database();
        $stream = new Stream();
        $stream->registerGate(new CreateTableParseGate());
        $stream->registerGate(new CreateTableExecuteGate($db));
        $stream->registerGate(new ResultGate());
        
        // Create first time
        $sql = "CREATE TABLE users (id, name)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        // Try with IF NOT EXISTS
        $stream2 = new Stream();
        $stream2->registerGate(new CreateTableParseGate());
        $stream2->registerGate(new CreateTableExecuteGate($db));
        $stream2->registerGate(new ResultGate());
        
        $sql2 = "CREATE TABLE IF NOT EXISTS users (id, email)";
        $stream2->emit(new Event($sql2, $stream2->getId()));
        $stream2->process();
        
        $result = $stream2->getResult();
        $this->assert(str_contains($result, 'already exists'), "Already exists message");
        $this->assert(str_contains($result, 'skipped'), "Skipped indicator");
        $this->assert(!str_contains($result, 'ERROR'), "No error");
        
        echo "\n";
    }
    
    private function test_invalid_syntax(): void {
        echo "--- Invalid Syntax ---\n";
        
        $db = new Database();
        $stream = new Stream();
        $stream->registerGate(new CreateTableParseGate());
        $stream->registerGate(new CreateTableExecuteGate($db));
        $stream->registerGate(new ResultGate());
        
        $sql = "CREATE TABLE users";  // Missing column list
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'ERROR'), "Error for invalid syntax");
        
        echo "\n";
    }
    
    private function test_empty_column_list(): void {
        echo "--- Empty Column List ---\n";
        
        $db = new Database();
        $stream = new Stream();
        $stream->registerGate(new CreateTableParseGate());
        $stream->registerGate(new CreateTableExecuteGate($db));
        $stream->registerGate(new ResultGate());
        
        $sql = "CREATE TABLE users ()";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'ERROR'), "Error for empty column list");
        
        echo "\n";
    }
}

// Run tests
$test = new DDL_Phase1Test();
$test->run();
