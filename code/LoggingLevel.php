<?php

namespace SISODatabase;

/**
 * LoggingLevel - Constants for controlling logging verbosity
 */
class LoggingLevel {
    /**
     * No logging - maximum performance
     */
    public const NONE = 0;
    
    /**
     * Log gate names only - minimal overhead
     */
    public const MINIMAL = 1;
    
    /**
     * Log full transformation history - for debugging
     */
    public const DETAILED = 2;
}
