# SISO Database

**PHP implementation - SQL database engine using the SISO framework**

[![Paper](https://img.shields.io/badge/Paper-PDF-blue)](https://siso-framework.org/downloads/siso-paper.pdf)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

## Overview

Complete SQL database engine demonstrating the SISO (Stream In, Stream Out) framework. Implements DDL, DML, query processing, and JSON persistence.

Developed in **2 hours** using AI assistance, reaching **4,710 lines of code** with **305 passing tests**.

**Paper:** [SISO: A Pure Functional Framework for Rapid AI-Assisted Software Development](https://siso-framework.org/siso-paper.pdf)  
**Website:** [siso-framework.org](https://siso-framework.org)

## Features

### DDL (Data Definition Language)
- CREATE TABLE with constraints (PRIMARY KEY, NOT NULL, DEFAULT)
- Column types (INTEGER, TEXT, REAL, BLOB)
- DROP TABLE / DROP TABLE IF EXISTS

### DML (Data Manipulation Language)
- INSERT INTO ... VALUES (single and batch)
- INSERT with column names (any order, partial columns)
- SELECT * FROM table
- SELECT specific columns
- UPDATE ... SET ... WHERE
- DELETE FROM ... WHERE

### Query Features
- WHERE clauses (=, !=, <, >, <=, >=)
- AND/OR logic
- IN operator
- LIKE pattern matching
- BETWEEN ranges
- IS NULL / IS NOT NULL
- ORDER BY (ASC/DESC, NULL handling)
- LIMIT / OFFSET (pagination)
- DISTINCT (duplicate removal)

### Storage/Persistence
- SAVE database to JSON
- LOAD database from JSON
- Complete state preservation

## Quick Start

```php
<?php
require_once 'autoload.php';

use SISODatabase\Database;
use SISODatabase\Stream;
use SISODatabase\Event;
use SISODatabase\Gates\CreateTableParseGate;
use SISODatabase\Gates\InsertParseGate;
use SISODatabase\Gates\SelectParseGate;

$db = new Database();
$stream = new Stream();

// Register gates
$stream->registerGate(new CreateTableParseGate($db));
$stream->registerGate(new InsertParseGate($db));
$stream->registerGate(new SelectParseGate($db));
// ... register other gates

// Create table
$stream->emit(new Event('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, age INTEGER)', 'ddl'));
$stream->process();

// Insert data
$stream->emit(new Event("INSERT INTO users VALUES (1, 'Alice', 30)", 'dml'));
$stream->process();

// Query
$stream->emit(new Event('SELECT * FROM users WHERE age > 25', 'query'));
$stream->process();

echo $stream->getResult()->data;
```

## Installation

```bash
# Clone the repository
git clone https://github.com/siso-ai/siso-database.git
cd siso-database

# Install dependencies (optional - for PHPUnit)
composer install
```

## Requirements

- **PHP 8.1 or higher**
- No external dependencies for core functionality

## Testing

```bash
php tests/Core_Phase1Test.php
php tests/DDL_AllPhasesTest.php
php tests/Insert_AllPhasesTest.php
php tests/Select_AllPhasesTest.php
php tests/Where_AllPhasesTest.php
php tests/Update_AllPhasesTest.php
php tests/Delete_AllPhasesTest.php
php tests/Storage_Phase1Test.php
```

**Results:** 305/305 tests passing ✓

### Test Breakdown

- Core Infrastructure: 42 tests
- DDL Operations: 64 tests
- INSERT Operations: 35 tests
- SELECT Operations: 31 tests
- WHERE Clauses: 48 tests
- UPDATE Operations: 28 tests
- DELETE Operations: 24 tests
- Storage/Persistence: 33 tests

## Examples

### Create Table

```php
$stream->emit(new Event(
    'CREATE TABLE products (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        price REAL,
        stock INTEGER DEFAULT 0
    )',
    'ddl'
));
```

### Insert Data

```php
// Single insert
$stream->emit(new Event(
    "INSERT INTO products VALUES (1, 'Widget', 19.99, 100)",
    'dml'
));

// Batch insert
$stream->emit(new Event(
    "INSERT INTO products (name, price) VALUES 
     ('Gadget', 29.99),
     ('Tool', 14.99),
     ('Device', 39.99)",
    'dml'
));
```

### Query Data

```php
// Simple SELECT
$stream->emit(new Event('SELECT * FROM products', 'query'));

// With WHERE clause
$stream->emit(new Event(
    'SELECT name, price FROM products WHERE price < 30',
    'query'
));

// With ORDER BY and LIMIT
$stream->emit(new Event(
    'SELECT * FROM products ORDER BY price DESC LIMIT 10',
    'query'
));
```

### Update and Delete

```php
// Update
$stream->emit(new Event(
    "UPDATE products SET stock = stock - 1 WHERE id = 1",
    'dml'
));

// Delete
$stream->emit(new Event(
    "DELETE FROM products WHERE stock = 0",
    'dml'
));
```

### Persistence

```php
// Save database
$stream->emit(new Event('SAVE mydb.json', 'storage'));

// Load database
$stream->emit(new Event('LOAD mydb.json', 'storage'));
```

## Architecture

```
SQL Statement (Event)
  ↓
Parse Gate (CREATE/INSERT/SELECT/UPDATE/DELETE)
  ↓
Execute Gate (modify database)
  ↓
Query Processing Gates (WHERE/ORDER BY/LIMIT/DISTINCT)
  ↓
Result Gate (format output)
  ↓
Result (formatted table or success message)
```

## File Structure

```
siso-database/
├── code/
│   ├── Gates/               # 21 specialized gates
│   │   ├── CreateTableParseGate.php
│   │   ├── InsertParseGate.php
│   │   ├── SelectParseGate.php
│   │   ├── FilterGate.php   # WHERE processing
│   │   └── ... (17 more)
│   ├── Database.php         # Main database class
│   ├── StorageEngine.php    # JSON persistence
│   ├── TableSchema.php
│   ├── Table.php
│   ├── Row.php
│   ├── WhereClause.php
│   ├── Event.php
│   ├── Stream.php
│   └── ...
├── tests/                   # 10 comprehensive test files
├── demos/                   # 8 demo files
├── autoload.php
└── composer.json
```

## Performance

- Simple queries: <1ms
- Complex WHERE clauses: 1-5ms
- Large result sets (1000+ rows): 10-50ms
- All operations use pure functional transformations

## Related Implementations

- **[Logic Prover](https://github.com/siso-ai/siso-logic-prover)** (JavaScript) - Automated theorem proving
- **[Math CAS](https://github.com/siso-ai/siso-math-cas)** (PHP) - Symbolic mathematics through calculus
- **[Framework](https://github.com/siso-ai/siso-framework)** - Core SISO framework specification

## Citation

```bibtex
@misc{bailey2025siso,
  title={SISO: A Pure Functional Framework for Rapid AI-Assisted Software Development},
  author={Bailey, Jonathan},
  year={2025},
  url={https://siso-framework.org}
}
```

## License

MIT License - See [LICENSE](LICENSE) file

Copyright (c) 2025 Jonathan Bailey

## Contact

- **Issues**: [GitHub Issues](https://github.com/siso-ai/siso-database/issues)
- **Website**: [siso-framework.org](https://siso-framework.org)
