<?php

require_once 'autoload.php';

use SISODatabase\Event;
use SISODatabase\Stream;
use SISODatabase\Database;
use SISODatabase\StorageEngine;
use SISODatabase\Gates\CreateTableParseGate;
use SISODatabase\Gates\CreateTableExecuteGate;
use SISODatabase\Gates\InsertParseGate;
use SISODatabase\Gates\InsertExecuteGate;
use SISODatabase\Gates\SelectParseGate;
use SISODatabase\Gates\TableScanGate;
use SISODatabase\Gates\FilterGate;
use SISODatabase\Gates\ProjectionGate;
use SISODatabase\Gates\OrderByGate;
use SISODatabase\Gates\ResultSetGate;
use SISODatabase\Gates\SaveGate;
use SISODatabase\Gates\LoadGate;
use SISODatabase\Gates\ResultGate;

echo "=== SISO Database - Storage Demo (Phase 1: JSON) ===\n";
echo "Persistent Database Storage\n\n";

// Create database and stream
$db = new Database();
$stream = new Stream();

// Register all gates
$stream->registerGate(new CreateTableParseGate());
$stream->registerGate(new CreateTableExecuteGate($db));
$stream->registerGate(new InsertParseGate());
$stream->registerGate(new InsertExecuteGate($db));
$stream->registerGate(new SelectParseGate());
$stream->registerGate(new TableScanGate($db));
$stream->registerGate(new FilterGate());
$stream->registerGate(new ProjectionGate());
$stream->registerGate(new OrderByGate());
$stream->registerGate(new ResultSetGate());
$stream->registerGate(new SaveGate($db));
$stream->registerGate(new LoadGate($db));
$stream->registerGate(new ResultGate());

// Helper function
function execute($sql, $stream) {
    $stream->emit(new Event($sql, $stream->getId()));
    $stream->process();
    return $stream->getResult();
}

// Database filename
$dbFile = '/tmp/demo-library';

// Clean up any existing file
$storage = new StorageEngine();
if ($storage->exists($dbFile)) {
    $storage->delete($dbFile);
}

echo "=== Creating and Populating Database ===\n\n";

// Create tables
echo "Creating 'books' table...\n";
$sql = "CREATE TABLE books (id INTEGER PRIMARY KEY, title TEXT NOT NULL, author TEXT NOT NULL, year INTEGER, isbn TEXT, available INTEGER DEFAULT 1)";
execute($sql, $stream);

echo "Creating 'members' table...\n";
$sql = "CREATE TABLE members (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT, joined TEXT)";
execute($sql, $stream);

echo "\nInserting book data...\n";
$books = [
    "INSERT INTO books VALUES (1, '1984', 'George Orwell', 1949, '978-0452284234', 1)",
    "INSERT INTO books VALUES (2, 'Brave New World', 'Aldous Huxley', 1932, '978-0060850524', 1)",
    "INSERT INTO books VALUES (3, 'Fahrenheit 451', 'Ray Bradbury', 1953, '978-1451673319', 0)",
    "INSERT INTO books VALUES (4, 'The Handmaid''s Tale', 'Margaret Atwood', 1985, '978-0385490818', 1)",
    "INSERT INTO books VALUES (5, 'Neuromancer', 'William Gibson', 1984, '978-0441569595', 1)"
];

foreach ($books as $sql) {
    execute($sql, $stream);
}

echo "Inserting member data...\n";
$members = [
    "INSERT INTO members VALUES (1, 'Alice Johnson', 'alice@example.com', '2023-01-15')",
    "INSERT INTO members VALUES (2, 'Bob Smith', 'bob@example.com', '2023-03-22')",
    "INSERT INTO members VALUES (3, 'Carol White', 'carol@example.com', '2023-06-10')"
];

foreach ($members as $sql) {
    execute($sql, $stream);
}

echo "\nDatabase populated with 5 books and 3 members.\n\n";

// Query before saving
echo "=== Query Before Saving ===\n\n";
$sql = "SELECT title, author, year FROM books WHERE available = 1 ORDER BY year ASC";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n";

// Save database
echo "=== Saving Database to Disk ===\n\n";
$sql = "SAVE DATABASE '{$dbFile}'";
echo "SQL: {$sql}\n";
$result = execute($sql, $stream);
echo $result . "\n\n";

// Get file info
$fileSize = $storage->getFileSize($dbFile);
echo "File: {$dbFile}.sisodb\n";
echo "Size: " . number_format($fileSize) . " bytes (" . number_format($fileSize / 1024, 2) . " KB)\n";
echo "Tables: {$db->getTableCount()}\n";
echo "Books: {$db->getTable('books')->count()} rows\n";
echo "Members: {$db->getTable('members')->count()} rows\n\n";

// Show JSON contents (first 500 chars)
echo "=== JSON File Format (excerpt) ===\n";
$json = file_get_contents($dbFile . '.sisodb');
echo substr($json, 0, 500) . "...\n\n";

// Simulate app restart - clear database
echo "=== Simulating Application Restart ===\n";
echo "Clearing in-memory database...\n";
$db->clear();
echo "Tables in memory: {$db->getTableCount()}\n\n";

// Load database
echo "=== Loading Database from Disk ===\n\n";
$sql = "LOAD DATABASE '{$dbFile}'";
echo "SQL: {$sql}\n";
$result = execute($sql, $stream);
echo $result . "\n\n";

echo "Database restored!\n";
echo "Tables: {$db->getTableCount()}\n";
echo "Books: {$db->getTable('books')->count()} rows\n";
echo "Members: {$db->getTable('members')->count()} rows\n\n";

// Query after loading
echo "=== Query After Loading ===\n\n";
$sql = "SELECT title, author, year FROM books WHERE available = 1 ORDER BY year ASC";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n";

// Demonstrate schema preservation
echo "=== Schema Preservation Test ===\n\n";
$booksSchema = $db->getTableSchema('books');
echo "Table: books\n";
echo "Columns: " . implode(', ', $booksSchema->getColumnNames()) . "\n";
echo "Primary Key: " . $booksSchema->getPrimaryKey() . "\n";

$columns = $booksSchema->getColumns();
echo "\nColumn Details:\n";
foreach ($columns as $name => $col) {
    $constraints = [];
    if ($col->primaryKey) $constraints[] = 'PRIMARY KEY';
    if ($col->notNull) $constraints[] = 'NOT NULL';
    if ($col->defaultValue !== null) $constraints[] = "DEFAULT {$col->defaultValue}";
    
    $constraintStr = empty($constraints) ? '' : ' (' . implode(', ', $constraints) . ')';
    echo "  - {$col->name}: {$col->type}{$constraintStr}\n";
}
echo "\n";

// Demonstrate multiple save/load cycles
echo "=== Multiple Save/Load Cycles ===\n\n";

echo "Adding new book...\n";
$sql = "INSERT INTO books VALUES (6, 'Snow Crash', 'Neal Stephenson', 1992, '978-0553380958', 1)";
execute($sql, $stream);

$sql = "SAVE DATABASE '{$dbFile}'";
execute($sql, $stream);
echo "Saved (6 books)\n\n";

echo "Adding another book...\n";
$sql = "INSERT INTO books VALUES (7, 'The Diamond Age', 'Neal Stephenson', 1995, '978-0553380965', 1)";
execute($sql, $stream);

$sql = "SAVE DATABASE '{$dbFile}'";
execute($sql, $stream);
echo "Saved (7 books)\n\n";

// Final query
echo "=== Final State ===\n\n";
$sql = "SELECT COUNT(*) as total FROM books";
echo "Note: COUNT not implemented yet, using manual count\n";
echo "Total books in database: {$db->getTable('books')->count()}\n\n";

$sql = "SELECT title, author, year FROM books WHERE year >= 1980 ORDER BY year DESC";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n";

// Storage engine methods
echo "=== StorageEngine Methods ===\n\n";
echo "exists('{$dbFile}'): " . ($storage->exists($dbFile) ? 'true' : 'false') . "\n";
echo "getFileSize('{$dbFile}'): " . number_format($storage->getFileSize($dbFile)) . " bytes\n";
echo "\nDeleting database file...\n";
$storage->delete($dbFile);
echo "exists('{$dbFile}'): " . ($storage->exists($dbFile) ? 'true' : 'false') . "\n\n";

echo "=== Demo Complete ===\n";
echo "Storage Phase 1 (JSON) features:\n";
echo "✓ Save database to .sisodb JSON file\n";
echo "✓ Load database from file\n";
echo "✓ All schemas preserved (columns, types, constraints)\n";
echo "✓ All data preserved (including NULLs, types)\n";
echo "✓ Multiple tables supported\n";
echo "✓ File operations (exists, delete, getFileSize)\n";
echo "✓ SQL commands: SAVE DATABASE, LOAD DATABASE\n";
echo "✓ Human-readable JSON format\n";
echo "✓ Version tracking for compatibility\n";
echo "\nDatabase is now persistent! 💾\n";
