# Schema Management Guide

## Overview

The `create_tables_v2.sql` file contains the complete database schema for new store databases. This schema is automatically applied when a new store is created via the signup process.

## Files

- **`create_tables_v2.sql`** - Complete schema file (ready to use)
- **`extract_schema_improved.php`** - Script to regenerate schema from wep02_v2 database
- **`app/helpers/schema_executor.php`** - Helper function to execute schema files

## Tables Included

The schema includes all necessary tables:

1. `users` - Store users and administrators
2. `customers` - Customer information
3. `categories` - Product categories
4. `suppliers` - Product suppliers
5. `products` - Product inventory
6. `invoices` - Sales invoices
7. `invoice_items` - Invoice line items
8. `expenses` - Store expenses
9. `inventory_movements` - Inventory movement history
10. `bookings` - Booking appointments
11. `notifications` - System notifications

Plus views:
- `daily_bookings_view`
- `weekly_bookings_view`
- `top_booking_types_view`

## Regenerating Schema

If you need to regenerate the schema from the live `wep02_v2` database:

```bash
php sql/extract_schema_improved.php > sql/create_tables_v2.sql
```

This will:
- Extract all table structures
- Include all indexes and foreign keys
- Add DROP TABLE IF EXISTS for idempotency
- Include all views
- Output clean, executable SQL

## Using the Schema

The schema is automatically applied when:
1. A new store is created via signup
2. The signup process calls `executeSchemaFile()` from `app/helpers/schema_executor.php`

### Manual Execution

To manually apply the schema to a database:

```php
require_once 'app/helpers/schema_executor.php';

$pdo = new PDO("mysql:host=localhost;dbname=your_database;charset=utf8mb4", 'root', '');
executeSchemaFile($pdo, 'sql/create_tables_v2.sql', 'your_database');
```

## Schema Features

- **Idempotent**: Safe to run multiple times (DROP TABLE IF EXISTS)
- **UTF8MB4**: Full Unicode support
- **Foreign Keys**: Proper referential integrity
- **Indexes**: Optimized for performance
- **ENUMs**: Type-safe status fields
- **Timestamps**: Automatic created_at/updated_at tracking

## Verification

After applying the schema, verify all tables exist:

```sql
SHOW TABLES;
```

Should show all 11 tables plus 3 views.

## Troubleshooting

### Error: Table already exists
- This is normal if running the schema multiple times
- The DROP TABLE IF EXISTS handles this automatically

### Error: Foreign key constraint fails
- Ensure tables are created in the correct order
- The schema file handles dependencies automatically

### Missing tables after signup
- Check error logs for schema execution errors
- Verify `create_tables_v2.sql` exists and is readable
- Check database permissions

