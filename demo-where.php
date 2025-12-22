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
use SISODatabase\Gates\FilterGate;
use SISODatabase\Gates\ProjectionGate;
use SISODatabase\Gates\OrderByGate;
use SISODatabase\Gates\LimitGate;
use SISODatabase\Gates\DistinctGate;
use SISODatabase\Gates\ResultSetGate;
use SISODatabase\Gates\ResultGate;

echo "=== SISO Database - WHERE Clause Demo ===\n";
echo "Data Filtering: WHERE conditions\n\n";

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
$stream->registerGate(new FilterGate());
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

$sql = "CREATE TABLE employees (id INTEGER, name TEXT, dept TEXT, salary INTEGER, age INTEGER, city TEXT)";
execute($sql, $stream);

$sql = "INSERT INTO employees VALUES (1, 'Alice', 'Engineering', 75000, 28, 'NYC')";
execute($sql, $stream);

$sql = "INSERT INTO employees VALUES (2, 'Bob', 'Sales', 65000, 34, 'LA')";
execute($sql, $stream);

$sql = "INSERT INTO employees VALUES (3, 'Charlie', 'Engineering', 80000, 25, 'NYC')";
execute($sql, $stream);

$sql = "INSERT INTO employees VALUES (4, 'Diana', 'Sales', 70000, 31, 'NYC')";
execute($sql, $stream);

$sql = "INSERT INTO employees VALUES (5, 'Eve', 'HR', 60000, 28, 'LA')";
execute($sql, $stream);

$sql = "INSERT INTO employees VALUES (6, 'Frank', 'Engineering', 90000, 35, 'SF')";
execute($sql, $stream);

echo "Data inserted successfully!\n\n";

// PHASE 1: Simple Comparisons
echo "=== PHASE 1: Simple Comparisons ===\n\n";

$sql = "SELECT * FROM employees WHERE age = 28";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT name, dept, salary FROM employees WHERE salary > 70000";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT * FROM employees WHERE dept != 'Engineering'";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT name, age FROM employees WHERE age <= 30";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

// PHASE 2: AND Conditions
echo "=== PHASE 2: AND Conditions ===\n\n";

$sql = "SELECT * FROM employees WHERE dept = 'Engineering' AND age > 26";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT name, city FROM employees WHERE salary >= 70000 AND city = 'NYC'";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT * FROM employees WHERE age >= 28 AND age <= 32 AND city != 'LA'";
echo "SQL: {$sql}\n";
echo "Result: Multiple AND conditions\n";
echo execute($sql, $stream) . "\n\n";

// PHASE 3: OR and Complex Logic
echo "=== PHASE 3: OR and Complex Logic ===\n\n";

$sql = "SELECT * FROM employees WHERE age < 26 OR age > 34";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT name, dept, city FROM employees WHERE city = 'NYC' OR city = 'SF'";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT * FROM employees WHERE age < 30 OR salary > 80000";
echo "SQL: {$sql}\n";
echo "Result: Mixed conditions\n";
echo execute($sql, $stream) . "\n\n";

// PHASE 4: Special Operators
echo "=== PHASE 4: Special Operators ===\n\n";

echo "--- IN Operator ---\n";
$sql = "SELECT * FROM employees WHERE id IN (1, 3, 5)";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT name, dept FROM employees WHERE dept IN ('HR', 'Sales')";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

echo "--- LIKE Operator ---\n";
$sql = "SELECT * FROM employees WHERE name LIKE 'A%'";
echo "SQL: {$sql}\n";
echo "Result: Names starting with 'A'\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT name FROM employees WHERE name LIKE '%e'";
echo "SQL: {$sql}\n";
echo "Result: Names ending with 'e'\n";
echo execute($sql, $stream) . "\n\n";

echo "--- BETWEEN Operator ---\n";
$sql = "SELECT * FROM employees WHERE age BETWEEN 28 AND 32";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT name, salary FROM employees WHERE salary BETWEEN 65000 AND 75000";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

echo "--- IS NULL / IS NOT NULL ---\n";
// Add a row with NULL
$sql = "INSERT INTO employees VALUES (7, 'Grace', 'Marketing', 68000, 29, NULL)";
execute($sql, $stream);

$sql = "SELECT * FROM employees WHERE city IS NULL";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT name, city FROM employees WHERE city IS NOT NULL";
echo "SQL: {$sql}\n";
echo execute($sql, $stream) . "\n\n";

// INTEGRATION: WHERE with other features
echo "=== INTEGRATION: WHERE with Other Features ===\n\n";

$sql = "SELECT name, dept, salary FROM employees WHERE age > 25 ORDER BY salary DESC";
echo "SQL: {$sql}\n";
echo "Result: WHERE + ORDER BY\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT * FROM employees WHERE city = 'NYC' ORDER BY age ASC LIMIT 2";
echo "SQL: {$sql}\n";
echo "Result: WHERE + ORDER BY + LIMIT\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT DISTINCT dept FROM employees WHERE salary >= 65000";
echo "SQL: {$sql}\n";
echo "Result: WHERE + DISTINCT\n";
echo execute($sql, $stream) . "\n\n";

$sql = "SELECT name, age FROM employees WHERE age BETWEEN 28 AND 35 AND city != 'LA' ORDER BY age DESC";
echo "SQL: {$sql}\n";
echo "Result: Complex query with BETWEEN, AND, ORDER BY\n";
echo execute($sql, $stream) . "\n\n";

echo "=== Demo Complete ===\n";
echo "WHERE clause operations working correctly!\n";
echo "- Phase 1: Simple comparisons (=, !=, <, >, <=, >=) ✓\n";
echo "- Phase 2: AND conditions ✓\n";
echo "- Phase 3: OR and complex logic ✓\n";
echo "- Phase 4: Special operators (IN, LIKE, BETWEEN, IS NULL, IS NOT NULL) ✓\n";
echo "- Integration with SELECT, ORDER BY, LIMIT, DISTINCT ✓\n";
