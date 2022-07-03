<?php
/**
 * A trait which provides retry logic for a class or trait.
 *
 * @author  Zachary K. Watkins, zwatkins.it@gmail.com
 * @package ThoughtfulWeb\Tools
 * @license MIT
 */

namespace ThoughtfulWeb\Tools\Database\Traits;

/**
 * Retry logic for database commands which may encounter transient connection errors.
 */
trait Retries {
	protected static $timeouts = [];
	protected static $pauses = [5000,6000,10000,15000,30000,60000];
	protected function setPDOTimeout( $timeout )
	{

	}
	public static function retry( callable $command, $timeout = null ) {
		if ($timeout !== null && is_numeric($timeout)){
			self::setPDOTimeout(intval($timeout));
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
			}, function($attempt) use (self::$pauses, &$attempt_i){
				$attempt_i = $attempt;
				return self::$pauses[$attempt-1];
			}, function ($exception) use ($logPrefix, self::$pauses, $attempt_i) {
				app_log_exception($exception, $logPrefix, '', 'error', $attempt_i, 5, self::$pauses[$attempt_i-1]);
				return true;
			});
			if (!app_try_db_connection($logPrefix)) {
				throw $exception;
			}
			DB::reconnect();

			// Execute the command.
			$attempt_i = 1;
			$result = retry(5, $command, function($attempt) use (self::$pauses, &$attempt_i){
				$attempt_i = $attempt;
				return self::$pauses[$attempt-1];
			}, function ($exception) use ($logPrefix, self::$pauses, $attempt_i) {
				app_log_exception($exception, $logPrefix, '', 'error', $attempt_i, 5, self::$pauses[$attempt_i-1]);
				return true;
			});
			if (null !== $timeout) {
				app_reset_pdo_sqlsrv_timeout();
			}
			return $result;
		}
	}
}
