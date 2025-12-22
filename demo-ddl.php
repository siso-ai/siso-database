<?php

require_once 'autoload.php';

use SISODatabase\Event;
use SISODatabase\Stream;
use SISODatabase\Database;
use SISODatabase\Gates\CreateTableParseGate;
use SISODatabase\Gates\CreateTableExecuteGate;
use SISODatabase\Gates\DropTableGate;
use SISODatabase\Gates\ResultGate;
use SISODatabase\Gates\ErrorGate;
use SISODatabase\LoggingLevel;

echo "=== SISO Database - DDL Demo ===\n";
echo "Data Definition Language: CREATE and DROP TABLE\n\n";

// Create database and stream
$db = new Database();
$stream = new Stream();
$stream->setLoggingLevel(LoggingLevel::MINIMAL);

// Register gates
$stream->registerGate(new CreateTableParseGate());
$stream->registerGate(new CreateTableExecuteGate($db));
$stream->registerGate(new DropTableGate($db));
$stream->registerGate(new ResultGate());
$stream->registerGate(new ErrorGate(false)); // Development mode

// Helper function
function execute($sql, $stream) {
    $stream->emit(new Event($sql, $stream->getId()));
    $stream->process();
    return $stream->getResult();
}

// PHASE 1: Basic CREATE TABLE
echo "=== PHASE 1: Basic CREATE TABLE ===\n\n";

$sql = "CREATE TABLE users (id, name, email)";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n";
echo "Tables: " . implode(', ', $db->getTableNames()) . "\n\n";

// PHASE 2: Column Types
echo "=== PHASE 2: Column Types ===\n\n";

$sql = "CREATE TABLE products (id INTEGER, name TEXT, price REAL, image BLOB)";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n";

$schema = $db->getTableSchema('products');
echo "Schema: {$schema}\n\n";

// PHASE 3: PRIMARY KEY
echo "=== PHASE 3: PRIMARY KEY ===\n\n";

$sql = "CREATE TABLE customers (id INTEGER PRIMARY KEY, name TEXT, email TEXT)";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n";

$schema = $db->getTableSchema('customers');
echo "Primary Key: " . ($schema->getPrimaryKey() ?? 'none') . "\n";
echo "Schema: {$schema}\n\n";

// PHASE 4: NOT NULL and DEFAULT
echo "=== PHASE 4: NOT NULL and DEFAULT ===\n\n";

$sql = "CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT NOT NULL, status TEXT DEFAULT draft, views INTEGER DEFAULT 0)";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n";

$schema = $db->getTableSchema('posts');
echo "Schema: {$schema}\n";

$titleCol = $schema->getColumn('title');
echo "Title NOT NULL: " . ($titleCol->notNull ? 'yes' : 'no') . "\n";

$statusCol = $schema->getColumn('status');
echo "Status DEFAULT: " . ($statusCol->defaultValue ?? 'none') . "\n";

$viewsCol = $schema->getColumn('views');
echo "Views DEFAULT: " . ($viewsCol->defaultValue ?? 'none') . "\n\n";

// CREATE TABLE IF NOT EXISTS
echo "=== IF NOT EXISTS ===\n\n";

$sql = "CREATE TABLE IF NOT EXISTS users (id, username)";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n\n";

// Try to create existing table (error)
echo "=== Error Handling ===\n\n";

$sql = "CREATE TABLE users (different, columns)";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n\n";

// PHASE 5: DROP TABLE
echo "=== PHASE 5: DROP TABLE ===\n\n";

$sql = "DROP TABLE products";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n";
echo "Tables: " . implode(', ', $db->getTableNames()) . "\n\n";

// DROP TABLE IF EXISTS
$sql = "DROP TABLE IF EXISTS nonexistent";
echo "SQL: {$sql}\n";
echo "Result: " . execute($sql, $stream) . "\n\n";

// Show database info
echo "=== Final Database State ===\n\n";

$info = $db->getInfo();
echo "Total Tables: {$info['total_tables']}\n\n";

foreach ($info['tables'] as $tableName => $tableInfo) {
    echo "Table: {$tableName}\n";
    echo "  Columns: {$tableInfo['columns']}\n";
    echo "  Column Names: " . implode(', ', $tableInfo['column_names']) . "\n";
    echo "  Primary Key: " . ($tableInfo['primary_key'] ?? 'none') . "\n";
    echo "  Rows: {$tableInfo['row_count']}\n";
    echo "\n";
}

echo "=== Demo Complete ===\n";
echo "DDL operations working correctly!\n";
