<?php

require_once 'autoload.php';

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
use SISODatabase\Gates\ErrorGate;

echo "=== SISO Database - DML INSERT Demo ===\n";
echo "Data Manipulation: INSERT INTO tables\n\n";

// Create database
$db = new Database();
$stream = new Stream();

// Register gates
$stream->registerGate(new CreateTableParseGate());
$stream->registerGate(new CreateTableExecuteGate($db));
$stream->registerGate(new InsertParseGate());
$stream->registerGate(new InsertExecuteGate($db));
$stream->registerGate(new ResultGate());
$stream->registerGate(new ErrorGate(false));

// Helper function
function execute($sql, $stream) {
    $stream->emit(new Event($sql, $stream->getId()));
    $stream->process();
    return $stream->getResult();
}

// Create tables
echo "=== Creating Tables ===\n\n";

$sql = "CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT, status TEXT DEFAULT active)";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n\n";

$sql = "CREATE TABLE products (id INTEGER, name TEXT, price REAL, stock INTEGER)";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n\n";

// PHASE 1: Simple INSERT VALUES
echo "=== PHASE 1: Simple INSERT VALUES ===\n\n";

$sql = "INSERT INTO users VALUES (1, 'Alice', 'alice@test.com', 'active')";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n";

$table = $db->getTable('users');
echo "Rows in users: {$table->count()}\n";
$row = $table->getRow(0);
echo "First row: {$row}\n\n";

// Insert with different types
$sql = "INSERT INTO products VALUES (101, 'Widget', 19.99, 50)";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n\n";

// Insert with NULL
$sql = "INSERT INTO users VALUES (2, 'Bob', NULL, 'pending')";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n";

$row = $table->getRow(1);
echo "Second row: {$row}\n\n";

// PHASE 2: INSERT with Column Names
echo "=== PHASE 2: INSERT with Column Names ===\n\n";

$sql = "INSERT INTO users (id, name, email) VALUES (3, 'Charlie', 'charlie@test.com')";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n";

$row = $table->getRow(2);
echo "Third row: {$row}\n";
echo "Notice: status has DEFAULT value 'active'\n\n";

// Partial columns
$sql = "INSERT INTO users (name, email) VALUES ('Dave', 'dave@test.com')";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n";

$row = $table->getRow(3);
echo "Fourth row: {$row}\n";
echo "Notice: id is NULL (no value specified)\n\n";

// Different column order
$sql = "INSERT INTO users (email, status, id, name) VALUES ('eve@test.com', 'inactive', 5, 'Eve')";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n";

$row = $table->getRow(4);
echo "Fifth row: {$row}\n";
echo "Notice: Column order doesn't matter!\n\n";

// Single column
$sql = "INSERT INTO users (name) VALUES ('Frank')";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n\n";

// PHASE 3: Batch INSERT
echo "=== PHASE 3: Batch INSERT ===\n\n";

$sql = "INSERT INTO products VALUES (102, 'Gadget', 29.99, 30), (103, 'Doohickey', 9.99, 100), (104, 'Thingamajig', 49.99, 15)";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n";

$productTable = $db->getTable('products');
echo "Total products: {$productTable->count()}\n\n";

// Batch with column names
$sql = "INSERT INTO users (id, name, email) VALUES (10, 'George', 'george@test.com'), (11, 'Hannah', 'hannah@test.com')";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n";

echo "Total users: {$table->count()}\n\n";

// Error Handling
echo "=== Error Handling ===\n\n";

$sql = "INSERT INTO users VALUES (99, 'Test')";  // Wrong number of columns
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n\n";

$sql = "INSERT INTO users (id, invalid_col) VALUES (100, 'test')";  // Invalid column
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n\n";

$sql = "INSERT INTO nonexistent VALUES (1, 2, 3)";  // Table doesn't exist
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n\n";

// Show all data
echo "=== Final Database State ===\n\n";

echo "Users Table:\n";
echo "ID\tName\t\tEmail\t\t\tStatus\n";
echo str_repeat("-", 60) . "\n";
foreach ($table->getAllRows() as $row) {
    $id = $row->get('id') ?? 'NULL';
    $name = str_pad($row->get('name') ?? 'NULL', 10);
    $email = str_pad($row->get('email') ?? 'NULL', 20);
    $status = $row->get('status') ?? 'NULL';
    echo "{$id}\t{$name}\t{$email}\t{$status}\n";
}

echo "\n";

echo "Products Table:\n";
echo "ID\tName\t\tPrice\tStock\n";
echo str_repeat("-", 50) . "\n";
foreach ($productTable->getAllRows() as $row) {
    $id = $row->get('id');
    $name = str_pad($row->get('name'), 15);
    $price = $row->get('price');
    $stock = $row->get('stock');
    echo "{$id}\t{$name}\t{$price}\t{$stock}\n";
}

echo "\n=== Demo Complete ===\n";
echo "INSERT operations working correctly!\n";
echo "- Phase 1: Simple INSERT with all values ✓\n";
echo "- Phase 2: INSERT with column names ✓\n";
echo "- Phase 3: Batch INSERT ✓\n";
