<?php

/**
 * Simple autoloader for SISO Database
 */
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    // SISODatabase\Event -> code/Event.php
    // SISODatabase\Gates\ResultGate -> code/Gates/ResultGate.php
    
    $prefix = 'SISODatabase\\';
    $base_dir = __DIR__ . '/code/';
    
    // Check if class uses our namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Get relative class name
    $relative_class = substr($class, $len);
    
    // Replace namespace separators with directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
