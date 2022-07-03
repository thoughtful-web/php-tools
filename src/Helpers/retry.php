<?php
/**
 * Call a closure within resilient database connection logic.
 * According to Microsoft, the best practice for retry logic is as follows:
 * 1. If a database command fails due to a transient connection error,
 *    do not retry the command. Instead, close the connection and create a
 *    new one. Closing a PDO connection is done by calling DB::disconnect(),
 *    which nullifies the PDO object's variable assignment. This is how PHP
 *    official documentation says a connection can be closed.
 * 2. Do not retry a connection sooner than 5 seconds after it fails. This can
 *    degrade the service.
 * 3. Provide a timeout window of 15 to 30 seconds for a connection after a
 *    transient error occurs.
 * 4. A maximum wait time of between 1 and 2 minutes is very rare and occurs
 *    when the server needs to load balance before allowing a connection to
 *    succeed.
 */
if (!function_exists('app_resilient_db_command')) {
    function app_resilient_db_command(callable $command, $timeout = null) {
        $waits = [5000,6000,10000,15000,30000,60000];
        if ($timeout !== null && is_numeric($timeout)){
            app_log_dev(__FUNCTION__.':'.__FILE__.':'.__LINE__);
            app_set_pdo_sqlsrv_timeout(intval($timeout));
        }
        $logPrefix = '[app_resilient_db_command]';
        $exception = null;
        try {
            // Execute the command.
            $result = $command();
            if (null !== $timeout) {
                app_reset_pdo_sqlsrv_timeout();
            }
            return $result;
        } catch (\Throwable $exception) {
            app_log_exception($exception, $logPrefix, 'The command failed the first time it was called.');
            if (!$exception instanceof \PDOException) {
                throw $exception;
            }
        }
        // Ensure the last character in the log prefix is a space.
        $logPrefix = !$logPrefix ? '' : trim( $logPrefix ) . ' ';
        // We have a PDO exception.
        // Detect list of transient error codes.
        $is_transient_error = app_is_transient_error($exception->getCode());
        if (true !== $is_transient_error) {
            // Not a transient error, this function should not handle it.
            app_log_exception($exception, $logPrefix);
            throw $exception;
        }
        // We have a transient PDO exception.
        // Microsoft says best practice is to disconnect and reconnect.
        app_log_exception($exception, $logPrefix, 'helpers:' . __LINE__);
        DB::disconnect();
        $attempt_i = 1;
        return retry(5, function(){
            app_log_dev(__FUNCTION__.':'.__FILE__.':'.__LINE__);
            app_set_pdo_sqlsrv_timeout(30);
        }, function($attempt) use ($waits, &$attempt_i){
            $attempt_i = $attempt;
            return $waits[$attempt-1];
        }, function ($exception) use ($logPrefix, $waits, $attempt_i) {
            app_log_exception($exception, $logPrefix, '', 'error', $attempt_i, 5, $waits[$attempt_i-1]);
            return true;
        });
        if (!app_try_db_connection($logPrefix)) {
            throw $exception;
        }
        DB::reconnect();

        // Execute the command.
        $attempt_i = 1;
        $result = retry(5, $command, function($attempt) use ($waits, &$attempt_i){
            $attempt_i = $attempt;
            return $waits[$attempt-1];
        }, function ($exception) use ($logPrefix, $waits, $attempt_i) {
            app_log_exception($exception, $logPrefix, '', 'error', $attempt_i, 5, $waits[$attempt_i-1]);
            return true;
        });
        if (null !== $timeout) {
            app_reset_pdo_sqlsrv_timeout();
        }
        return $result;
    }
}
if (!function_exists('app_is_transient_error')){
    /**
     * Detect if an error code represents a transient error.
     *
     * Returns a string describing the false condition or true if successful.
     *
     * @param string|int     $code      The error code.
     * @param Throwable|null $exception The exception thrown, if available.
     *
     * @return true | string
     */
    function app_is_transient_error($code, $exception = null) {
        $dbconnection = config('database.default');
        $transient_error_codes = config("database.connections.{$dbconnection}.transient_errors");
        if (!$transient_error_codes) {
            return "The connection {$dbconnection} is not configured to handle transient PDOExceptions.";
        }
        // Detect inclusion of error code in configuration.
        if (!in_array($code, $transient_error_codes, false)) {
            return "The error code \"$code\" is not a transient error.";
        } elseif ($code === 'HY000' && null !== $exception) {
            // Attempt to detect the nature of a variable error code.
            $message = $exception->getMessage();
            preg_match('/\[HY000\] ([^:]+):\s+([^\s]+)/', $matches);
            $preface = $matches[1];
            $subcode = $matches[2];
            if ($preface === 'Unable to connect') {
                return true;
            } elseif ($preface === 'General error') {
                $subcodes = config("database.connections.{$dbconnection}.transient_suberrors");
                if (isset($subcodes[$code]) && !in_array($subcode, $subcodes[$code], false)) {
                    return "The error code \"$code:$subcode\" is not a transient error.";
                }
            }
        }
        // It is a transient error.
        return true;
    }
}
if (!function_exists('app_set_pdo_sqlsrv_timeout')) {
    /**
     * Set the timeout for the SQL Server database connection.
     *
     * @param int $timeout The new timeout value in seconds.
     *
     * @return bool
     */
    function app_set_pdo_sqlsrv_timeout($timeout = 15) {
        app_log_dev('Setting database connection timeout to ' . $timeout);
        $pdo_timeout_const = 0;
        if (defined('PDO::SQLSRV_ATTR_QUERY_TIMEOUT')){
            app_log_dev('PDO::SQLSRV_ATTR_QUERY_TIMEOUT is defined');
            $pdo_timeout_const = PDO::SQLSRV_ATTR_QUERY_TIMEOUT;
        } elseif (defined('PDO::ATTR_TIMEOUT')) {
            Log::error('Could not set the database connection timeout to ' . $timeout . ' because PDO::SQLSRV_ATTR_QUERY_TIMEOUT is not defined. Using PDO::ATTR_TIMEOUT instead. ' . __LINE__);
            $pdo_timeout_const = PDO::ATTR_TIMEOUT;
        } else {
            Log::error('PDO timeout constants could not be found.');
        }
        $sqlsrv_options = config('database.connections.sqlsrv.options', array());
        if (isset($sqlsrv_options[$pdo_timeout_const]) && $sqlsrv_options[$pdo_timeout_const] === $timeout) {
            app_log_dev('The new timeout is identical to the old timeout.');
            return false;
        }
        if (array_key_exists($pdo_timeout_const, $sqlsrv_options)) {
            $old_timeout = $sqlsrv_options[$pdo_timeout_const];
            app_log_dev('The configured timeout is ' . $old_timeout . ' ' . __LINE__);
        } else {
            // It is not configured, so we retrieve it directly from the class.
            try {
                $old_timeout = DB::connection()->getPdo()->getAttribute($pdo_timeout_const);
                app_log_dev('The PDO object\'s timeout is ' . $old_timeout . ' ' . __LINE__);
            } catch ( \Exception $e ) {
                Log::error('The current timeout could not be detected.');
                app_log_exception($e);
            }
            if (!isset($old_timeout) || !$old_timeout) {
                // No timeout.
                $old_timeout = 0;
            }
        }
        if ($timeout !== $old_timeout) {
            app_log_dev('Updating timeout');
            // Update the app configuration.
            $sqlsrv_options[$pdo_timeout_const] = $timeout;
            config(['database.connections.sqlsrv.options' => $sqlsrv_options]);
            // Update the connection attributes.
            DB::connection()->getPdo()->setAttribute($pdo_timeout_const, $timeout);
            $new_timeout = 'unset';
            // Detect known issue with local development database driver.
            try {
                $new_timeout = DB::connection()->getPdo()->getAttribute($pdo_timeout_const);
                Log::debug('Timeout updated on PDO connection, new value: ' . $new_timeout . ' (' . __LINE__ . ')');
            } catch (\Exception $e) {
                if (
                    false !== strpos('Driver does not support this function', $e->getMessage())
                    && false !== strpos('driver does not support that attribute', $e->getMessage())
                ) {
                    if (App::environment(['local', 'development'])) {
                        Log::error($e->getMessage());
                    } else {
                        app_log_exception($e);
                    }
                }
                Log::debug('Timeout updated on PDO connection, new value: ' . $new_timeout . ' (' . __LINE__ . ')');
            }
            return true;
        }
        return false;
    }
}
if (!function_exists('app_reset_pdo_sqlsrv_timeout')) {
    /**
     * Restore the timeout for the SQL Server database connection.
     *
     * @return int | false
     */
    function app_reset_pdo_sqlsrv_timeout() {
        $original_timeout = config('database.connections.sqlsrv.original_timeout', false);
        if (false === $original_timeout) {
            return false;
        }
        app_log_dev(__FUNCTION__.':'.__FILE__.':'.__LINE__);
        app_set_pdo_sqlsrv_timeout($original_timeout);
        return $original_timeout;
    }
}
/**
 * Test the database connection and provide feedback if it isn't available.
 * This implements best practices for cloud architecture according to Microsoft documentation.
 *
 * @return bool | int
 */
if (!function_exists('app_try_db_connection')) {
    function app_try_db_connection($logPrefix = '[app_try_db_connection] ', $limit = null, $attempt = null) {
        $waits = [5000,6000,10000,15000,30000,60000];
        try {

            DB::connection()->getPdo();

            return true;
        } catch(\Exception $e) {
            /**
             * The connection either failed or timed out.
             * Best practice:
             * 1. Make a new connection.
             * 2. Do not reattempt the connection sooner than 5 seconds.
             * 3. Do not delay more than 2 minutes.
             */
            sleep(5);
            try {
                retry(5, function() {

                    DB::reconnect();

                }, function ($attempt) use ($waits){
                    $times = [25,30,30,45,60,120];
                    if (extension_loaded('pdo_sqlsrv')) {
                        app_log_dev(__FUNCTION__.':'.__FILE__.':'.__LINE__);
                        app_set_pdo_sqlsrv_timeout($times[$attempt - 1]);
                    }
                    return $waits[$attempts - 1];
                }, function ($exception) {
                    app_log_exception($exception, $logPrefix, $msg, 'error', $attempt, $limit, $delay);
                    return true === app_is_transient_error($exception->getCode());
                });
            } catch (\Exception $ex) {
                // Release the job back to the queue for another try.
                // Reset the PDO timeout.
                app_reset_pdo_sqlsrv_timeout();
                $delay = 120;
                // Delay the retry for twice the maximum wait time.
                $msg = __FUNCTION__ . '() encountered an exception';
                app_log_exception($ex, $logPrefix, $msg, 'error', $attempt, $limit, $delay);
                // Return either a delay in seconds or an instruction to stop retrying.
                if (is_int($limit) && is_int($attempt) && $attempt < $limit) {
                    return $delay;
                } else {
                    return false;
                }
            }
        }
        return true;
    }
}
if (!function_exists('app_log_exception')){
    function app_log_exception($exception, $tag = '', $msg = '', $level = 'error', $attempt = null, $limit = null, $delay = null){
        $code = $exception->getCode();
        // Detect if it was a transient error.
        $is_transient_error = app_is_transient_error($code);
        // Sometimes show stack traces.
        $show_stacktrace = true;
        $class = get_class($exception);
        // Add the attempt number to the provided prefix.
        if (intval($attempt)) {
            $tag .= '#' . strval($attempt);
            if (intval($limit)) {
                $tag .= '/' . strval($limit);
            }
        }
        // Extend the log prefix with additional information.
        $pfx = [
            true === $is_transient_error ? 'transient' : '',
            ltrim(strtolower(substr($class, strrpos($class,'\\'))), '\\'),
            strval($code),
            get_exception_file_slug($exception)
        ];
        if ($tag) {
            $pfx[3] .= ':' . trim($tag, '[]');
        }
        if (App::environment(['local'])) {
            Log::debug($pfx);
        }
        $pfx = array_filter($pfx);
        $prefix = '[' . implode('][', $pfx) . ']';
        if (App::environment(['local'])) {
            Log::debug($prefix);
        }
        if (is_int($attempt) && is_int($limit)) {
            $remaining = $limit - $attempt;
            if ($msg) {
                $msg .= ', ';
            }
            if (App::environment(['local'])) {
                Log::debug($remaining . " = $limit - $attempt");
            }
            $msg .= $remaining ? "will retry {$remaining} more times" : 'no retries remain';
            if ($remaining && strval($delay)) {
                $msg .= " in {$delay} seconds";
            }
            $msg .= '.';
        }
        // Modify the error level.
        if (is_int($attempt) && is_int($limit) && ($limit - $attempt)) {
            $level = 'debug';
        }
        // Combine the message data.
        $m  = trim($prefix);
        $m .= ' ';
        $m .= $msg . "\n";
        $m .= 'Cause: ' . $exception->getMessage() . "\n";
        $m .= 'Class: ' . get_class($exception) . "\n";
        $m .= 'File: ' . $exception->getFile() . ':' . $exception->getLine() . "\n";
        if ($show_stacktrace) {
            $m .= "Trace: \n" . $exception->getTraceAsString();
        }
        // Remove server directories from the error message.
        $m = str_replace(dirname(__DIR__), '', $m);
        // Log the message.
        Log::$level($m);
    }
}
if (!function_exists('get_exception_file_slug')) {
    function get_exception_file_slug(\Exception $exception){
        $suspect = null;
        $traces = $exception->getTrace();
        foreach ($traces as $key => $trace) {
            if (isset($trace['file']) && false !== strpos($trace['file'], 'wwwroot/app/')) {
                $suspect = $trace['file'];
                break;
            }
        }
        if (null === $suspect) {
            // Not sure why this would occur, dump the traces.
            Log::error($traces);
            $suspect = 'traces_not_found';
        }
        return app_string_to_slug($suspect);
    }
}
if (!function_exists('app_string_to_slug')) {
    function app_string_to_slug($long_string, $ideal_slug_length = 8, $min_part_length = 4) {
        if (false !== strpos($long_string, '.php')) {
            $path = mb_convert_encoding($long_string, 'utf-8', mb_detect_encoding($long_string));
            $long_string = basename($path, '.php');
        }
        if (strlen($long_string) <= $ideal_slug_length) {
            return strtolower($long_string);
        }
        $pattern = '/[A-Z]{1}([a-z]+|[A-Z]+)/';
        preg_match_all($pattern, $long_string, $parts);
        $parts = $parts[0];
        if (strlen(implode($parts)) > $ideal_slug_length) {
            $gates = [];
            $i = -1;
            while (count($gates) < count($parts)) {
                $i = $i < count($parts) - 1 ? $i + 1 : 0;
                // If the string part has finished evaluation, continue to the next part.
                if (isset($gates[$i])){
                    continue;
                } elseif (ctype_upper($parts[$i]) || strlen($parts[$i]) <= $min_part_length) {
                    // Accept the part if its length is minimal or the part is uppercase.
                    $gates[$i] = true;
                    continue;
                }
                $parts[$i] = substr($parts[$i], 0, -1);
            }
        }
        return strtolower(implode($parts));
    }
}
if (!function_exists('app_log_dev')) {
    function app_log_dev($message, $type = 'debug') {
        if (App::environment(['local', 'development'])) {
            Log::$type($message);
        }
    }
}