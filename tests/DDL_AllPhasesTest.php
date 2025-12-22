<?php

require_once __DIR__ . '/../autoload.php';

use SISODatabase\Event;
use SISODatabase\Stream;
use SISODatabase\Database;
use SISODatabase\Gates\CreateTableParseGate;
use SISODatabase\Gates\CreateTableExecuteGate;
use SISODatabase\Gates\DropTableGate;
use SISODatabase\Gates\ResultGate;

/**
 * DDL Phases 2-5 Tests
 * 
 * Phase 2: Column Types
 * Phase 3: PRIMARY KEY
 * Phase 4: NOT NULL and DEFAULT
 * Phase 5: DROP TABLE
 */
class DDL_AllPhasesTest {
    private int $passed = 0;
    private int $failed = 0;
    
    public function run(): void {
        echo "=== DDL All Phases Tests ===\n\n";
        
        // Phase 2: Column Types
        echo "=== PHASE 2: Column Types ===\n";
        $this->test_integer_type();
        $this->test_text_type();
        $this->test_real_type();
        $this->test_blob_type();
        $this->test_mixed_types();
        $this->test_case_insensitive_types();
        $this->test_default_type();
        echo "\n";
        
        // Phase 3: PRIMARY KEY
        echo "=== PHASE 3: PRIMARY KEY ===\n";
        $this->test_primary_key_integer();
        $this->test_primary_key_without_type();
        $this->test_multiple_primary_keys_error();
        $this->test_table_without_primary_key();
        echo "\n";
        
        // Phase 4: NOT NULL and DEFAULT
        echo "=== PHASE 4: NOT NULL and DEFAULT ===\n";
        $this->test_not_null_constraint();
        $this->test_default_value();
        $this->test_not_null_and_default();
        $this->test_primary_key_implies_not_null();
        echo "\n";
        
        // Phase 5: DROP TABLE
        echo "=== PHASE 5: DROP TABLE ===\n";
        $this->test_drop_table();
        $this->test_drop_table_if_exists();
        $this->test_drop_nonexistent_table();
        $this->test_drop_if_exists_nonexistent();
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
    
    // PHASE 2 TESTS: Column Types
    
    private function test_integer_type(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "CREATE TABLE users (id INTEGER, age INTEGER)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $schema = $db->getTableSchema('users');
        $this->assert($schema->getColumnType('id') === 'INTEGER', "id is INTEGER");
        $this->assert($schema->getColumnType('age') === 'INTEGER', "age is INTEGER");
    }
    
    private function test_text_type(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "CREATE TABLE users (name TEXT, email TEXT)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $schema = $db->getTableSchema('users');
        $this->assert($schema->getColumnType('name') === 'TEXT', "name is TEXT");
        $this->assert($schema->getColumnType('email') === 'TEXT', "email is TEXT");
    }
    
    private function test_real_type(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "CREATE TABLE products (price REAL, weight REAL)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $schema = $db->getTableSchema('products');
        $this->assert($schema->getColumnType('price') === 'REAL', "price is REAL");
        $this->assert($schema->getColumnType('weight') === 'REAL', "weight is REAL");
    }
    
    private function test_blob_type(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "CREATE TABLE files (data BLOB, thumbnail BLOB)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $schema = $db->getTableSchema('files');
        $this->assert($schema->getColumnType('data') === 'BLOB', "data is BLOB");
        $this->assert($schema->getColumnType('thumbnail') === 'BLOB', "thumbnail is BLOB");
    }
    
    private function test_mixed_types(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "CREATE TABLE mixed (id INTEGER, name TEXT, price REAL, data BLOB)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $schema = $db->getTableSchema('mixed');
        $this->assert($schema->getColumnType('id') === 'INTEGER', "id is INTEGER");
        $this->assert($schema->getColumnType('name') === 'TEXT', "name is TEXT");
        $this->assert($schema->getColumnType('price') === 'REAL', "price is REAL");
        $this->assert($schema->getColumnType('data') === 'BLOB', "data is BLOB");
    }
    
    private function test_case_insensitive_types(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "CREATE TABLE test (a integer, b Text, c REAL, d blob)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $schema = $db->getTableSchema('test');
        $this->assert($schema->getColumnType('a') === 'INTEGER', "lowercase integer");
        $this->assert($schema->getColumnType('b') === 'TEXT', "mixed case Text");
        $this->assert($schema->getColumnType('c') === 'REAL', "uppercase REAL");
        $this->assert($schema->getColumnType('d') === 'BLOB', "lowercase blob");
    }
    
    private function test_default_type(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "CREATE TABLE users (id, name)";  // No types specified
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $schema = $db->getTableSchema('users');
        $this->assert($schema->getColumnType('id') === 'TEXT', "Default type is TEXT");
        $this->assert($schema->getColumnType('name') === 'TEXT', "Default type is TEXT");
    }
    
    // PHASE 3 TESTS: PRIMARY KEY
    
    private function test_primary_key_integer(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $schema = $db->getTableSchema('users');
        $this->assert($schema->hasPrimaryKey(), "Has primary key");
        $this->assert($schema->getPrimaryKey() === 'id', "Primary key is 'id'");
        
        $idCol = $schema->getColumn('id');
        $this->assert($idCol->primaryKey === true, "id column is primary key");
    }
    
    private function test_primary_key_without_type(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "CREATE TABLE users (id PRIMARY KEY, name TEXT)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $schema = $db->getTableSchema('users');
        $this->assert($schema->getPrimaryKey() === 'id', "Primary key without type");
    }
    
    private function test_multiple_primary_keys_error(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "CREATE TABLE bad (id INTEGER PRIMARY KEY, code INTEGER PRIMARY KEY)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'ERROR'), "Error for multiple PRIMARY KEYs");
        $this->assert(str_contains($result, 'only one'), "Correct error message");
    }
    
    private function test_table_without_primary_key(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "CREATE TABLE logs (timestamp TEXT, message TEXT)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $schema = $db->getTableSchema('logs');
        $this->assert(!$schema->hasPrimaryKey(), "No primary key");
        $this->assert($schema->getPrimaryKey() === null, "getPrimaryKey returns null");
    }
    
    // PHASE 4 TESTS: NOT NULL and DEFAULT
    
    private function test_not_null_constraint(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "CREATE TABLE users (id INTEGER, name TEXT NOT NULL)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $schema = $db->getTableSchema('users');
        $nameCol = $schema->getColumn('name');
        $this->assert($nameCol->notNull === true, "name is NOT NULL");
        $this->assert(!$nameCol->allowsNull(), "name doesn't allow NULL");
    }
    
    private function test_default_value(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "CREATE TABLE users (status TEXT DEFAULT active, count INTEGER DEFAULT 0)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $schema = $db->getTableSchema('users');
        $statusCol = $schema->getColumn('status');
        $countCol = $schema->getColumn('count');
        
        $this->assert($statusCol->hasDefault(), "status has default");
        $this->assert($statusCol->defaultValue === 'active', "status default is 'active'");
        $this->assert($countCol->defaultValue === '0', "count default is '0'");
    }
    
    private function test_not_null_and_default(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "CREATE TABLE users (name TEXT NOT NULL DEFAULT unknown)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $schema = $db->getTableSchema('users');
        $nameCol = $schema->getColumn('name');
        
        $this->assert($nameCol->notNull === true, "Has NOT NULL");
        $this->assert($nameCol->defaultValue === 'unknown', "Has DEFAULT");
    }
    
    private function test_primary_key_implies_not_null(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "CREATE TABLE users (id INTEGER PRIMARY KEY)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $schema = $db->getTableSchema('users');
        $idCol = $schema->getColumn('id');
        
        $this->assert($idCol->notNull === true, "PRIMARY KEY implies NOT NULL");
    }
    
    // PHASE 5 TESTS: DROP TABLE
    
    private function test_drop_table(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        // Create table
        $sql = "CREATE TABLE users (id, name)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $this->assert($db->hasTable('users'), "Table created");
        
        // Drop table
        $stream2 = $this->createStream($db);
        $sql2 = "DROP TABLE users";
        $stream2->emit(new Event($sql2, $stream2->getId()));
        $stream2->process();
        
        $this->assert(!$db->hasTable('users'), "Table dropped");
        $this->assert($stream2->getResult() === "Table 'users' dropped", "Success message");
    }
    
    private function test_drop_table_if_exists(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        // Create table
        $sql = "CREATE TABLE users (id, name)";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        // Drop with IF EXISTS
        $stream2 = $this->createStream($db);
        $sql2 = "DROP TABLE IF EXISTS users";
        $stream2->emit(new Event($sql2, $stream2->getId()));
        $stream2->process();
        
        $this->assert(!$db->hasTable('users'), "Table dropped");
    }
    
    private function test_drop_nonexistent_table(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "DROP TABLE nonexistent";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'ERROR'), "Error for nonexistent table");
        $this->assert(str_contains($result, 'does not exist'), "Correct error message");
    }
    
    private function test_drop_if_exists_nonexistent(): void {
        $db = new Database();
        $stream = $this->createStream($db);
        
        $sql = "DROP TABLE IF EXISTS nonexistent";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'does not exist'), "Nonexistent mentioned");
        $this->assert(str_contains($result, 'skipped'), "Skipped indicator");
        $this->assert(!str_contains($result, 'ERROR'), "No error");
    }
    
    // Helper method
    private function createStream(Database $db): Stream {
        $stream = new Stream();
        $stream->registerGate(new CreateTableParseGate());
        $stream->registerGate(new CreateTableExecuteGate($db));
        $stream->registerGate(new DropTableGate($db));
        $stream->registerGate(new ResultGate());
        return $stream;
    }
}

// Run tests
$test = new DDL_AllPhasesTest();
$test->run();
