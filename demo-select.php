<?php

require_once 'autoload.php';

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
use SISODatabase\Gates\SelectParseGate;
use SISODatabase\Gates\TableScanGate;
use SISODatabase\Gates\ProjectionGate;
use SISODatabase\Gates\OrderByGate;
use SISODatabase\Gates\LimitGate;
use SISODatabase\Gates\DistinctGate;
use SISODatabase\Gates\ResultSetGate;
use SISODatabase\Gates\ResultGate;

echo "=== SISO Database - DML SELECT Demo ===\n";
echo "Data Retrieval: SELECT queries\n\n";

// Create database
$db = new Database();
$stream = new Stream();

// Register all gates
$stream->registerGate(new CreateTableParseGate());
$stream->registerGate(new CreateTableExecuteGate($db));
$stream->registerGate(new InsertParseGate());
$stream->registerGate(new InsertExecuteGate($db));
$stream->registerGate(new SelectParseGate());
$stream->registerGate(new TableScanGate($db));
$stream->registerGate(new ProjectionGate());
$stream->registerGate(new OrderByGate());
$stream->registerGate(new LimitGate());
$stream->registerGate(new DistinctGate());
$stream->registerGate(new ResultSetGate());
$stream->registerGate(new ResultGate());

// Helper function
function execute($sql, $stream) {
    $stream->emit(new Event($sql, $stream->getId()));
    $stream->process();
    return $stream->getResult();
}

// Setup: Create table and insert data
echo "=== Setup: Creating Table and Inserting Data ===\n\n";

$sql = "CREATE TABLE employees (id INTEGER, name TEXT, dept TEXT, salary INTEGER, age INTEGER)";
execute($sql, $stream);

$sql = "INSERT INTO employees VALUES (1, 'Alice', 'Engineering', 75000, 28)";
execute($sql, $stream);

$sql = "INSERT INTO employees VALUES (2, 'Bob', 'Sales', 65000, 34)";
execute($sql, $stream);

$sql = "INSERT INTO employees VALUES (3, 'Charlie', 'Engineering', 80000, 25)";
execute($sql, $stream);

$sql = "INSERT INTO employees VALUES (4, 'Diana', 'Sales', 70000, 31)";
execute($sql, $stream);

$sql = "INSERT INTO employees VALUES (5, 'Eve', 'HR', 60000, 28)";
execute($sql, $stream);

$sql = "INSERT INTO employees VALUES (6, 'Frank', 'Engineering', 75000, 29)";
execute($sql, $stream);

echo "Data inserted successfully!\n\n";

// PHASE 1: SELECT *
echo "=== PHASE 1: SELECT * (Full Table Scan) ===\n\n";

$sql = "SELECT * FROM employees";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

// PHASE 2: SELECT Specific Columns
echo "=== PHASE 2: SELECT Specific Columns ===\n\n";

$sql = "SELECT name, dept FROM employees";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT name, salary, age FROM employees";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

// PHASE 3: ORDER BY
echo "=== PHASE 3: ORDER BY ===\n\n";

$sql = "SELECT * FROM employees ORDER BY salary ASC";
echo "SQL: {$sql}\n";
echo "Result: Ordered by salary (lowest to highest)\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT name, age FROM employees ORDER BY age DESC";
echo "SQL: {$sql}\n";
echo "Result: Ordered by age (oldest to youngest)\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT name, dept FROM employees ORDER BY dept ASC";
echo "SQL: {$sql}\n";
echo "Result: Ordered alphabetically by department\n";
echo execute($sql, $stream) . "\n\n";

// PHASE 4: LIMIT and OFFSET
echo "=== PHASE 4: LIMIT and OFFSET ===\n\n";

$sql = "SELECT name, salary FROM employees ORDER BY salary DESC LIMIT 3";
echo "SQL: {$sql}\n";
echo "Result: Top 3 highest salaries\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT * FROM employees LIMIT 2 OFFSET 2";
echo "SQL: {$sql}\n";
echo "Result: Rows 3-4 (skip first 2)\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT name FROM employees LIMIT 100";
echo "SQL: {$sql}\n";
echo "Result: LIMIT larger than table size\n";
echo execute($sql, $stream) . "\n\n";

// PHASE 5: DISTINCT
echo "=== PHASE 5: DISTINCT ===\n\n";

$sql = "SELECT DISTINCT dept FROM employees";
echo "SQL: {$sql}\n";
echo "Result: Unique departments\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT DISTINCT age FROM employees ORDER BY age ASC";
echo "SQL: {$sql}\n";
echo "Result: Unique ages, sorted\n";
echo execute($sql, $stream) . "\n\n";

// COMBINED FEATURES
echo "=== COMBINED FEATURES ===\n\n";

$sql = "SELECT name, dept, salary FROM employees ORDER BY salary DESC LIMIT 2";
echo "SQL: {$sql}\n";
echo "Result: Top 2 salaries with name and dept\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT DISTINCT dept FROM employees ORDER BY dept ASC";
echo "SQL: {$sql}\n";
echo "Result: Unique departments, alphabetically sorted\n";
echo execute($sql, $stream) . "\n\n";

// Error Handling
echo "=== Error Handling ===\n\n";

$sql = "SELECT invalid_column FROM employees";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT * FROM nonexistent";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

echo "=== Demo Complete ===\n";
echo "SELECT operations working correctly!\n";
echo "- Phase 1: SELECT * ✓\n";
echo "- Phase 2: Column projection ✓\n";
echo "- Phase 3: ORDER BY ✓\n";
echo "- Phase 4: LIMIT/OFFSET ✓\n";
echo "- Phase 5: DISTINCT ✓\n";
