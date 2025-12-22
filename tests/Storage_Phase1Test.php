<?php

require_once __DIR__ . '/../autoload.php';

use SISODatabase\Database;
use SISODatabase\TableSchema;
use SISODatabase\ColumnDefinition;
use SISODatabase\Row;
use SISODatabase\StorageEngine;
use SISODatabase\Event;
use SISODatabase\Stream;
use SISODatabase\Gates\SaveGate;
use SISODatabase\Gates\LoadGate;
use SISODatabase\Gates\ResultGate;

/**
 * Storage Phase 1 Tests
 * 
 * Tests JSON-based database persistence
 */
class Storage_Phase1Test {
    private int $passed = 0;
    private int $failed = 0;
    private string $testDir;
    
    public function __construct() {
        // Use /tmp for test files
        $this->testDir = '/tmp/siso-storage-test-' . uniqid();
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }
    }
    
    public function __destruct() {
        // Cleanup test files
        if (is_dir($this->testDir)) {
            $files = glob($this->testDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testDir);
        }
    }
    
    public function run(): void {
        echo "=== Storage Phase 1 Tests (JSON) ===\n\n";
        
        echo "=== Basic Save/Load ===\n";
        $this->test_save_and_load_empty_database();
        $this->test_save_and_load_with_data();
        $this->test_save_and_load_multiple_tables();
        echo "\n";
        
        echo "=== Data Preservation ===\n";
        $this->test_preserve_data_types();
        $this->test_preserve_null_values();
        $this->test_preserve_schema_constraints();
        echo "\n";
        
        echo "=== File Operations ===\n";
        $this->test_file_exists();
        $this->test_file_delete();
        $this->test_file_size();
        $this->test_file_extension_added();
        echo "\n";
        
        echo "=== Error Handling ===\n";
        $this->test_load_nonexistent_file();
        $this->test_load_corrupt_file();
        echo "\n";
        
        echo "=== SQL Commands (Gates) ===\n";
        $this->test_save_gate_command();
        $this->test_load_gate_command();
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
    
    private function getTestFilePath(string $name): string {
        return $this->testDir . '/' . $name;
    }
    
    // BASIC SAVE/LOAD TESTS
    
    private function test_save_and_load_empty_database(): void {
        $db = new Database();
        $storage = new StorageEngine();
        $filename = $this->getTestFilePath('empty');
        
        // Save empty database
        $storage->save($db, $filename);
        
        // Load it back
        $loadedDb = $storage->load($filename);
        
        $this->assert($loadedDb->getTableCount() === 0, "Empty database saved and loaded");
    }
    
    private function test_save_and_load_with_data(): void {
        $db = new Database();
        
        // Create table with data
        $schema = new TableSchema('users');
        $schema->addColumnDefinition(new ColumnDefinition('id', 'INTEGER'));
        $schema->addColumnDefinition(new ColumnDefinition('name', 'TEXT'));
        $db->createTable($schema);
        
        $table = $db->getTable('users');
        $table->insert(new Row(['id' => 1, 'name' => 'Alice']));
        $table->insert(new Row(['id' => 2, 'name' => 'Bob']));
        
        // Save
        $storage = new StorageEngine();
        $filename = $this->getTestFilePath('with_data');
        $storage->save($db, $filename);
        
        // Load
        $loadedDb = $storage->load($filename);
        
        $this->assert($loadedDb->getTableCount() === 1, "Table count preserved");
        
        $loadedTable = $loadedDb->getTable('users');
        $this->assert($loadedTable !== null, "Table exists");
        $this->assert($loadedTable->count() === 2, "Row count preserved");
        
        $rows = $loadedTable->getAllRows();
        $this->assert($rows[0]->get('name') === 'Alice', "First row data preserved");
        $this->assert($rows[1]->get('name') === 'Bob', "Second row data preserved");
    }
    
    private function test_save_and_load_multiple_tables(): void {
        $db = new Database();
        
        // Create multiple tables
        $usersSchema = new TableSchema('users');
        $usersSchema->addColumnDefinition(new ColumnDefinition('id', 'INTEGER'));
        $usersSchema->addColumnDefinition(new ColumnDefinition('name', 'TEXT'));
        $db->createTable($usersSchema);
        
        $productsSchema = new TableSchema('products');
        $productsSchema->addColumnDefinition(new ColumnDefinition('id', 'INTEGER'));
        $productsSchema->addColumnDefinition(new ColumnDefinition('title', 'TEXT'));
        $productsSchema->addColumnDefinition(new ColumnDefinition('price', 'REAL'));
        $db->createTable($productsSchema);
        
        // Add data
        $db->getTable('users')->insert(new Row(['id' => 1, 'name' => 'Alice']));
        $db->getTable('products')->insert(new Row(['id' => 1, 'title' => 'Widget', 'price' => 19.99]));
        
        // Save and load
        $storage = new StorageEngine();
        $filename = $this->getTestFilePath('multiple_tables');
        $storage->save($db, $filename);
        $loadedDb = $storage->load($filename);
        
        $this->assert($loadedDb->getTableCount() === 2, "Multiple tables preserved");
        $this->assert($loadedDb->hasTable('users'), "users table exists");
        $this->assert($loadedDb->hasTable('products'), "products table exists");
        
        $this->assert($loadedDb->getTable('users')->count() === 1, "users data preserved");
        $this->assert($loadedDb->getTable('products')->count() === 1, "products data preserved");
    }
    
    // DATA PRESERVATION TESTS
    
    private function test_preserve_data_types(): void {
        $db = new Database();
        
        $schema = new TableSchema('types_test');
        $schema->addColumnDefinition(new ColumnDefinition('int_col', 'INTEGER'));
        $schema->addColumnDefinition(new ColumnDefinition('text_col', 'TEXT'));
        $schema->addColumnDefinition(new ColumnDefinition('real_col', 'REAL'));
        $db->createTable($schema);
        
        $db->getTable('types_test')->insert(new Row([
            'int_col' => 42,
            'text_col' => 'hello',
            'real_col' => 3.14
        ]));
        
        // Save and load
        $storage = new StorageEngine();
        $filename = $this->getTestFilePath('types');
        $storage->save($db, $filename);
        $loadedDb = $storage->load($filename);
        
        $row = $loadedDb->getTable('types_test')->getAllRows()[0];
        $this->assert($row->get('int_col') === 42, "Integer type preserved");
        $this->assert($row->get('text_col') === 'hello', "Text type preserved");
        $this->assert($row->get('real_col') === 3.14, "Real type preserved");
    }
    
    private function test_preserve_null_values(): void {
        $db = new Database();
        
        $schema = new TableSchema('nulls_test');
        $schema->addColumnDefinition(new ColumnDefinition('id', 'INTEGER'));
        $schema->addColumnDefinition(new ColumnDefinition('nullable', 'TEXT'));
        $db->createTable($schema);
        
        $db->getTable('nulls_test')->insert(new Row(['id' => 1, 'nullable' => null]));
        $db->getTable('nulls_test')->insert(new Row(['id' => 2, 'nullable' => 'not null']));
        
        // Save and load
        $storage = new StorageEngine();
        $filename = $this->getTestFilePath('nulls');
        $storage->save($db, $filename);
        $loadedDb = $storage->load($filename);
        
        $rows = $loadedDb->getTable('nulls_test')->getAllRows();
        $this->assert($rows[0]->get('nullable') === null, "NULL value preserved");
        $this->assert($rows[1]->get('nullable') === 'not null', "Non-null value preserved");
    }
    
    private function test_preserve_schema_constraints(): void {
        $db = new Database();
        
        $schema = new TableSchema('constraints_test');
        $schema->addColumnDefinition(new ColumnDefinition('id', 'INTEGER', true, false, null)); // PRIMARY KEY
        $schema->addColumnDefinition(new ColumnDefinition('name', 'TEXT', false, true, null)); // NOT NULL
        $schema->addColumnDefinition(new ColumnDefinition('status', 'TEXT', false, false, 'active')); // DEFAULT
        $db->createTable($schema);
        
        // Save and load
        $storage = new StorageEngine();
        $filename = $this->getTestFilePath('constraints');
        $storage->save($db, $filename);
        $loadedDb = $storage->load($filename);
        
        $loadedSchema = $loadedDb->getTableSchema('constraints_test');
        $columns = $loadedSchema->getColumns();
        
        $idCol = $columns['id'];
        $this->assert($idCol->primaryKey === true, "PRIMARY KEY preserved");
        
        $nameCol = $columns['name'];
        $this->assert($nameCol->notNull === true, "NOT NULL preserved");
        
        $statusCol = $columns['status'];
        $this->assert($statusCol->defaultValue === 'active', "DEFAULT value preserved");
    }
    
    // FILE OPERATIONS TESTS
    
    private function test_file_exists(): void {
        $db = new Database();
        $storage = new StorageEngine();
        $filename = $this->getTestFilePath('exists_test');
        
        $this->assert(!$storage->exists($filename), "File doesn't exist before save");
        
        $storage->save($db, $filename);
        
        $this->assert($storage->exists($filename), "File exists after save");
    }
    
    private function test_file_delete(): void {
        $db = new Database();
        $storage = new StorageEngine();
        $filename = $this->getTestFilePath('delete_test');
        
        $storage->save($db, $filename);
        $this->assert($storage->exists($filename), "File created");
        
        $deleted = $storage->delete($filename);
        $this->assert($deleted === true, "Delete returns true");
        $this->assert(!$storage->exists($filename), "File deleted");
    }
    
    private function test_file_size(): void {
        $db = new Database();
        $schema = new TableSchema('size_test');
        $schema->addColumnDefinition(new ColumnDefinition('id', 'INTEGER'));
        $db->createTable($schema);
        
        $storage = new StorageEngine();
        $filename = $this->getTestFilePath('size_test');
        
        $storage->save($db, $filename);
        $size = $storage->getFileSize($filename);
        
        $this->assert($size > 0, "File has non-zero size");
    }
    
    private function test_file_extension_added(): void {
        $db = new Database();
        $storage = new StorageEngine();
        $filename = $this->getTestFilePath('no_ext');
        
        $storage->save($db, $filename);
        
        // Check that .sisodb was added
        $this->assert(file_exists($filename . '.sisodb'), ".sisodb extension added");
    }
    
    // ERROR HANDLING TESTS
    
    private function test_load_nonexistent_file(): void {
        $storage = new StorageEngine();
        $filename = $this->getTestFilePath('nonexistent');
        
        try {
            $storage->load($filename);
            $this->assert(false, "Should throw exception for nonexistent file");
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'not found'), "Error message correct");
        }
    }
    
    private function test_load_corrupt_file(): void {
        $filename = $this->getTestFilePath('corrupt.sisodb');
        file_put_contents($filename, "This is not valid JSON");
        
        $storage = new StorageEngine();
        
        try {
            $storage->load($this->getTestFilePath('corrupt'));
            $this->assert(false, "Should throw exception for corrupt file");
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'Failed to parse'), "Error message correct");
        }
    }
    
    // SQL COMMANDS TESTS
    
    private function test_save_gate_command(): void {
        $db = new Database();
        $schema = new TableSchema('test');
        $schema->addColumnDefinition(new ColumnDefinition('id', 'INTEGER'));
        $db->createTable($schema);
        
        $stream = new Stream();
        $stream->registerGate(new SaveGate($db));
        $stream->registerGate(new ResultGate());
        
        $filename = $this->getTestFilePath('gate_test');
        $sql = "SAVE DATABASE '{$filename}'";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'saved'), "SAVE DATABASE command works");
        $this->assert(file_exists($filename . '.sisodb'), "File created by SAVE command");
    }
    
    private function test_load_gate_command(): void {
        // Create and save a database
        $db = new Database();
        $schema = new TableSchema('loadtest');
        $schema->addColumnDefinition(new ColumnDefinition('id', 'INTEGER'));
        $schema->addColumnDefinition(new ColumnDefinition('value', 'TEXT'));
        $db->createTable($schema);
        $db->getTable('loadtest')->insert(new Row(['id' => 99, 'value' => 'loaded']));
        
        $storage = new StorageEngine();
        $filename = $this->getTestFilePath('load_gate_test');
        $storage->save($db, $filename);
        
        // Create fresh database and load via command
        $db2 = new Database();
        $stream = new Stream();
        $stream->registerGate(new LoadGate($db2));
        $stream->registerGate(new ResultGate());
        
        $sql = "LOAD DATABASE '{$filename}'";
        $stream->emit(new Event($sql, $stream->getId()));
        $stream->process();
        
        $result = $stream->getResult();
        $this->assert(str_contains($result, 'loaded'), "LOAD DATABASE command works");
        $this->assert($db2->hasTable('loadtest'), "Table loaded into database");
        
        $row = $db2->getTable('loadtest')->getAllRows()[0];
        $this->assert($row->get('value') === 'loaded', "Data loaded correctly");
    }
}

// Run tests
$test = new Storage_Phase1Test();
$test->run();
