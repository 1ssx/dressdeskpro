<?php
/**
 * Schema Executor Helper
 * Executes SQL schema file on a database connection
 * Uses a simpler, more reliable approach
 */

function executeSchemaFile($pdo, $schemaFile, $targetDatabase = null) {
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: $schemaFile");
    }

    // If target database is specified, ensure we're using it
    if ($targetDatabase) {
        $pdo->exec("USE `$targetDatabase`");
    }

    // Read the SQL file
    $schemaSql = file_get_contents($schemaFile);
    
    if (empty($schemaSql)) {
        throw new Exception("Schema file is empty: $schemaFile");
    }
    
    // Remove MySQL-specific conditional directives (/*!40101 ... */)
    $schemaSql = preg_replace('/\/\*!40101[^*]*\*+(?:[^/*][^*]*\*+)*\//', '', $schemaSql);
    
    // Remove block comments (/* ... */) - be careful with nested
    $schemaSql = preg_replace('/\/\*[^*]*\*+(?:[^/*][^*]*\*+)*\//', '', $schemaSql);
    
    // Split into lines for processing
    $lines = explode("\n", $schemaSql);
    $sqlStatements = [];
    $currentStatement = '';
    $inString = false;
    $stringChar = '';
    
    foreach ($lines as $lineNum => $line) {
        $originalLine = $line;
        $line = rtrim($line); // Remove trailing whitespace
        
        // Skip completely empty lines
        if (empty($line)) {
            continue;
        }
        
        // Remove full-line comments (lines that start with -- after trimming)
        $trimmed = trim($line);
        if (preg_match('/^--/', $trimmed)) {
            continue; // Skip comment-only lines
        }
        
        // Process the line character by character to handle strings and comments
        $processedLine = '';
        $lineLength = strlen($line);
        
        for ($i = 0; $i < $lineLength; $i++) {
            $char = $line[$i];
            $prevChar = ($i > 0) ? $line[$i - 1] : '';
            $nextChar = ($i < $lineLength - 1) ? $line[$i + 1] : '';
            
            // Track string boundaries
            if (!$inString && ($char === '"' || $char === "'" || $char === '`')) {
                $inString = true;
                $stringChar = $char;
                $processedLine .= $char;
            } elseif ($inString && $char === $stringChar) {
                // Check if it's escaped
                if ($prevChar !== '\\') {
                    $inString = false;
                    $stringChar = '';
                }
                $processedLine .= $char;
            } elseif (!$inString && $char === '-' && $nextChar === '-') {
                // Found -- comment, stop processing this line
                break;
            } else {
                $processedLine .= $char;
            }
        }
        
        // Add the processed line to current statement
        if (!empty(trim($processedLine))) {
            $currentStatement .= $processedLine . "\n";
        }
        
        // Check if line ends with semicolon (end of statement)
        $trimmedProcessed = trim($processedLine);
        if (!empty($trimmedProcessed) && substr($trimmedProcessed, -1) === ';' && !$inString) {
            // End of statement
            $stmt = trim($currentStatement);
            if (!empty($stmt) && strlen($stmt) > 1) {
                $sqlStatements[] = $stmt;
            }
            $currentStatement = '';
        }
    }
    
    // Add any remaining statement
    if (!empty(trim($currentStatement))) {
        $stmt = trim($currentStatement);
        if (!empty($stmt) && strlen($stmt) > 1) {
            $sqlStatements[] = $stmt;
        }
    }
    
    // Debug: Log how many statements we found
    error_log("Schema parser: Found " . count($sqlStatements) . " SQL statements");
    if (count($sqlStatements) > 0) {
        error_log("First statement preview (100 chars): " . substr($sqlStatements[0], 0, 100));
    }
    
    if (count($sqlStatements) == 0) {
        // Fallback: Try to execute using MySQL command line if available
        error_log("WARNING: Parser found 0 statements. Trying fallback method...");
        
        // Clean the SQL for direct execution
        $cleanSql = $schemaSql;
        // Remove comments
        $cleanSql = preg_replace('/--.*$/m', '', $cleanSql);
        $cleanSql = preg_replace('/\/\*.*?\*\//s', '', $cleanSql);
        // Remove transaction control
        $cleanSql = preg_replace('/^(START\s+TRANSACTION|COMMIT);?$/mi', '', $cleanSql);
        
        // Split by semicolon (simple approach)
        $simpleStatements = array_filter(
            array_map('trim', explode(';', $cleanSql)),
            function($stmt) {
                return !empty($stmt) && strlen($stmt) > 5 && 
                       !preg_match('/^(SET|USE|START|COMMIT)/i', trim($stmt));
            }
        );
        
        if (count($simpleStatements) > 0) {
            error_log("Fallback method found " . count($simpleStatements) . " statements");
            $sqlStatements = array_values($simpleStatements);
        } else {
            error_log("ERROR: No statements found even with fallback. File size: " . strlen($schemaSql) . " bytes");
            error_log("First 500 chars: " . substr($schemaSql, 0, 500));
            throw new Exception("No SQL statements were found in schema file. File may be empty or incorrectly formatted.");
        }
    }
    
    // Execute each statement
    $errors = [];
    $criticalErrors = [];
    $executedCount = 0;
    $skippedCount = 0;
    
    foreach ($sqlStatements as $index => $statement) {
        $statement = trim($statement);
        
        // Skip empty statements
        if (empty($statement) || strlen($statement) <= 1) {
            $skippedCount++;
            continue;
        }
        
        // Skip MySQL-specific conditional directives
        if (preg_match('/^\/\*!40101/', $statement)) {
            $skippedCount++;
            continue;
        }
        
        // Handle SET statements
        if (preg_match('/^SET\s+/i', $statement)) {
            try {
                $pdo->exec($statement);
                $executedCount++;
            } catch (PDOException $e) {
                // Non-critical, log and continue
                error_log("SET statement warning: " . $e->getMessage());
            }
            continue;
        }
        
        // Skip transaction control statements
        if (preg_match('/^(START\s+TRANSACTION|COMMIT|ROLLBACK)/i', $statement)) {
            $skippedCount++;
            continue;
        }
        
        // Skip USE statements
        if (preg_match('/^USE\s+/i', $statement)) {
            $skippedCount++;
            continue;
        }
        
        // Execute the statement
        try {
            $pdo->exec($statement);
            $executedCount++;
            
            // Log important operations
            if (preg_match('/^CREATE\s+TABLE/i', $statement)) {
                if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?(\w+)[`"]?/i', $statement, $matches)) {
                    error_log("✓ Created table: " . $matches[1]);
                }
            } elseif (preg_match('/^DROP\s+TABLE/i', $statement)) {
                if (preg_match('/DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?[`"]?(\w+)[`"]?/i', $statement, $matches)) {
                    error_log("✓ Dropped table: " . $matches[1]);
                }
            } elseif (preg_match('/^CREATE\s+(?:OR\s+REPLACE\s+)?VIEW/i', $statement)) {
                if (preg_match('/CREATE\s+(?:OR\s+REPLACE\s+)?VIEW\s+[`"]?(\w+)[`"]?/i', $statement, $matches)) {
                    error_log("✓ Created view: " . $matches[1]);
                }
            }
        } catch (PDOException $e) {
            $errorCode = $e->getCode();
            $errorMsg = "Statement #" . ($index + 1) . " failed: " . $e->getMessage();
            error_log("✗ " . $errorMsg);
            error_log("   Statement (first 200 chars): " . substr($statement, 0, 200));
            
            // Critical errors
            if ($errorCode == 1064 || 
                (strpos($e->getMessage(), 'syntax error') !== false) ||
                (strpos($e->getMessage(), 'parse error') !== false)) {
                $criticalErrors[] = $errorMsg;
            } elseif ($errorCode == 1050 && strpos($statement, 'CREATE TABLE') !== false) {
                // Table already exists - might be OK
                error_log("   Warning: Table already exists");
                $errors[] = $errorMsg;
            } else {
                $errors[] = $errorMsg;
            }
        }
    }
    
    // Log execution summary
    error_log("Schema execution summary:");
    error_log("  - Total statements found: " . count($sqlStatements));
    error_log("  - Executed: $executedCount");
    error_log("  - Skipped: $skippedCount");
    error_log("  - Errors: " . count($errors));
    error_log("  - Critical errors: " . count($criticalErrors));
    
    // If we have critical errors, throw exception
    if (!empty($criticalErrors)) {
        throw new Exception("Critical schema errors: " . implode("; ", array_slice($criticalErrors, 0, 3)));
    }
    
    // If no statements were executed, that's a problem
    if ($executedCount == 0) {
        $debugInfo = "Found " . count($sqlStatements) . " statements, but none were executed. ";
        $debugInfo .= "Skipped: $skippedCount. ";
        if (count($sqlStatements) > 0) {
            $debugInfo .= "First statement type: " . substr(trim($sqlStatements[0]), 0, 50);
        }
        throw new Exception("No SQL statements were executed. $debugInfo");
    }
    
    // Log non-critical errors but don't fail
    if (!empty($errors)) {
        error_log("Schema execution completed with " . count($errors) . " non-critical errors");
    }
    
    return true;
}
