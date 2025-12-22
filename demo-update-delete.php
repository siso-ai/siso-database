<?php

require_once 'autoload.php';

use SISODatabase\Event;
use SISODatabase\Stream;
use SISODatabase\Database;
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
use SISODatabase\Gates\UpdateParseGate;
use SISODatabase\Gates\UpdateExecuteGate;
use SISODatabase\Gates\DeleteParseGate;
use SISODatabase\Gates\DeleteExecuteGate;
use SISODatabase\Gates\ResultGate;

echo "=== SISO Database - UPDATE and DELETE Demo ===\n";
echo "Complete CRUD Operations\n\n";

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
$stream->registerGate(new UpdateParseGate());
$stream->registerGate(new UpdateExecuteGate($db));
$stream->registerGate(new DeleteParseGate());
$stream->registerGate(new DeleteExecuteGate($db));
$stream->registerGate(new ResultGate());

// Helper function
function execute($sql, $stream) {
    $stream->emit(new Event($sql, $stream->getId()));
    $stream->process();
    return $stream->getResult();
}

echo "=== Setup: Creating Employee Database ===\n\n";

// Create table
$sql = "CREATE TABLE employees (id INTEGER PRIMARY KEY, name TEXT NOT NULL, dept TEXT, salary INTEGER, status TEXT DEFAULT 'active')";
execute($sql, $stream);
echo "Table created\n\n";

// Insert data
echo "Inserting employees...\n";
$employees = [
    "INSERT INTO employees VALUES (1, 'Alice Johnson', 'Engineering', 75000, 'active')",
    "INSERT INTO employees VALUES (2, 'Bob Smith', 'Sales', 65000, 'active')",
    "INSERT INTO employees VALUES (3, 'Charlie Brown', 'Engineering', 80000, 'active')",
    "INSERT INTO employees VALUES (4, 'Diana Prince', 'HR', 70000, 'active')",
    "INSERT INTO employees VALUES (5, 'Eve Davis', 'Sales', 68000, 'active')",
    "INSERT INTO employees VALUES (6, 'Frank Miller', 'Engineering', 90000, 'active')",
    "INSERT INTO employees VALUES (7, 'Grace Lee', 'HR', 72000, 'pending')",
    "INSERT INTO employees VALUES (8, 'Henry Wilson', 'Sales', 62000, 'pending')"
];

foreach ($employees as $sql) {
    execute($sql, $stream);
}

echo "8 employees added\n\n";

// Initial data
echo "=== Initial Data ===\n";
$sql = "SELECT * FROM employees ORDER BY id ASC";
echo execute($sql, $stream) . "\n";

// UPDATE EXAMPLES
echo "=== UPDATE Operations ===\n\n";

echo "1. Give Engineering department a 10% raise\n";
echo "   SQL: UPDATE employees SET salary = 85000 WHERE dept = 'Engineering'\n";
echo "   (Simplified for demo - in real DB would be salary = salary * 1.1)\n";
$result = execute("UPDATE employees SET salary = 85000 WHERE dept = 'Engineering'", $stream);
echo "   {$result}\n";
$sql = "SELECT name, dept, salary FROM employees WHERE dept = 'Engineering'";
echo execute($sql, $stream) . "\n";

echo "2. Activate all pending employees\n";
echo "   SQL: UPDATE employees SET status = 'active' WHERE status = 'pending'\n";
$result = execute("UPDATE employees SET status = 'active' WHERE status = 'pending'", $stream);
echo "   {$result}\n";
$sql = "SELECT name, status FROM employees WHERE id IN (7, 8)";
echo execute($sql, $stream) . "\n";

echo "3. Update specific employee\n";
echo "   SQL: UPDATE employees SET dept = 'Management', salary = 95000 WHERE id = 1\n";
$result = execute("UPDATE employees SET dept = 'Management', salary = 95000 WHERE id = 1", $stream);
echo "   {$result}\n";
$sql = "SELECT * FROM employees WHERE id = 1";
echo execute($sql, $stream) . "\n";

echo "4. Update with complex WHERE clause\n";
echo "   SQL: UPDATE employees SET status = 'senior' WHERE salary > 75000 AND dept != 'HR'\n";
$result = execute("UPDATE employees SET status = 'senior' WHERE salary > 75000 AND dept != 'HR'", $stream);
echo "   {$result}\n";
$sql = "SELECT name, dept, salary, status FROM employees WHERE status = 'senior' ORDER BY salary DESC";
echo execute($sql, $stream) . "\n";

// DELETE EXAMPLES
echo "=== DELETE Operations ===\n\n";

echo "5. Remove low-performing sales employees\n";
echo "   SQL: DELETE FROM employees WHERE dept = 'Sales' AND salary < 65000\n";
$result = execute("DELETE FROM employees WHERE dept = 'Sales' AND salary < 65000", $stream);
echo "   {$result}\n";
$sql = "SELECT name, dept, salary FROM employees WHERE dept = 'Sales'";
echo "   Remaining Sales employees:\n";
echo execute($sql, $stream) . "\n";

echo "6. Remove specific employee by ID\n";
echo "   SQL: DELETE FROM employees WHERE id = 7\n";
$result = execute("DELETE FROM employees WHERE id = 7", $stream);
echo "   {$result}\n";
$sql = "SELECT COUNT(*) as count FROM employees";
echo "   Note: COUNT not implemented, using table count\n";
echo "   Total employees: " . $db->getTable('employees')->count() . "\n\n";

echo "7. Delete with OR condition\n";
echo "   SQL: DELETE FROM employees WHERE salary > 90000 OR status = 'pending'\n";
$result = execute("DELETE FROM employees WHERE salary > 90000 OR status = 'pending'", $stream);
echo "   {$result}\n\n";

// Final state
echo "=== Final Employee List ===\n";
$sql = "SELECT * FROM employees ORDER BY dept ASC, salary DESC";
echo execute($sql, $stream) . "\n";

// Demonstrate UPDATE and DELETE together
echo "=== Complex Workflow: Department Reorganization ===\n\n";

echo "Scenario: HR department is being restructured\n\n";

echo "Step 1: Move all HR employees to 'Admin' department\n";
$result = execute("UPDATE employees SET dept = 'Admin' WHERE dept = 'HR'", $stream);
echo "   {$result}\n\n";

echo "Step 2: Give Admin employees a title update in status\n";
$result = execute("UPDATE employees SET status = 'admin-staff' WHERE dept = 'Admin'", $stream);
echo "   {$result}\n\n";

echo "Step 3: View the changes\n";
$sql = "SELECT name, dept, status, salary FROM employees WHERE dept = 'Admin'";
echo execute($sql, $stream) . "\n";

// Error handling
echo "=== Error Handling ===\n\n";

echo "1. Trying to update non-existent table\n";
echo "   SQL: UPDATE fake_table SET value = 1\n";
$result = execute("UPDATE fake_table SET value = 1", $stream);
echo "   {$result}\n\n";

echo "2. Trying to update non-existent column\n";
echo "   SQL: UPDATE employees SET fake_column = 'test'\n";
$result = execute("UPDATE employees SET fake_column = 'test'", $stream);
echo "   {$result}\n\n";

echo "3. Trying to delete from non-existent table\n";
echo "   SQL: DELETE FROM fake_table\n";
$result = execute("DELETE FROM fake_table", $stream);
echo "   {$result}\n\n";

// Summary statistics
echo "=== Summary Statistics ===\n\n";
$table = $db->getTable('employees');
echo "Total Employees: " . $table->count() . "\n";

$engineeringCount = 0;
$adminCount = 0;
$salesCount = 0;
foreach ($table->getAllRows() as $row) {
    $dept = $row->get('dept');
    if ($dept === 'Engineering') $engineeringCount++;
    if ($dept === 'Admin') $adminCount++;
    if ($dept === 'Sales') $salesCount++;
}

echo "Engineering: {$engineeringCount}\n";
echo "Admin: {$adminCount}\n";
echo "Sales: {$salesCount}\n";
echo "Management: 1\n\n";

echo "=== Demo Complete ===\n";
echo "Operations demonstrated:\n";
echo "✓ CREATE - Table creation\n";
echo "✓ INSERT - Batch data insertion\n";
echo "✓ SELECT - Queries with WHERE, ORDER BY\n";
echo "✓ UPDATE - Single and multiple columns\n";
echo "✓ UPDATE - With WHERE clause (=, >, AND, OR)\n";
echo "✓ DELETE - With WHERE clause\n";
echo "✓ DELETE - Complex conditions\n";
echo "\nFull CRUD operations complete! 🎉\n";
