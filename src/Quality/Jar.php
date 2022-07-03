<?php
/**
 * Debug helper class.
 *
 * @package ThoughtfulWeb\Tools
 * @author Zachary K. Watkins, zwatkins.it@gmail.com
 */

namespace ThoughtfulWeb\Tools\Quality;

class Jar {

	/**
	 * Whether the class is catching bugs.
	 *
	 * @var boolean|int
	 */
	public static $open = true;

	/**
	 * Bugs collected.
	 *
	 * @var array
	 */
	public static $caught = array();

	/**
	 * Listeners to call when a fatal bug is encountered.
	 */
	protected static $listeners = array();

	/**
	 * Catch bugs.
	 * Many file system functions return false on failure but also emit an E_WARNING message.
	 *
	 * Returns whether or not the state was changed by calling the function.
	 *
	 * @return bool
	 */
	public static function open() {
		if ( ! self::$open ) {
			self::$open   = true;
			self::$caught = array();
			set_error_handler( '\ThoughtfulWeb\Jar::error_handler' );
			set_exception_handler( '\ThoughtfulWeb\Jar::exception_handler' );
			register_shutdown_function( '\ThoughtfulWeb\Jar::shutdown_handler' );
			return true;
		}
		return false;
	}

	/**
	 * Release all of the bugs.
	 *
	 * @throws \Throwable The last bug caught.
	 *
	 * @return array
	 */
	public static function open_and_throw() {
		// Throw the last Throwable caught.
		$throwable_bug = null;
		$caught        = self::$caught;
		self::$caught  = array();
		foreach ( self::$caught as $bug ) {
			if ( $bug instanceof \Throwable ) {
				$throwable_bug = $bug;
				break;
			}
		}
		// Throw a throwable bug.
		if ( $throwable_bug ) {
			throw $throwable_bug;
		}
		return $caught;
	}

	/**
	 * Register events if a shutdown is caused by an error.
	 *
	 * Examples:
	 *
	 * Jar::on_fatal_shutdown( function( $bug ){
	 *     error_log( 'A fatal bug was encountered: ' . Jar::see_fatal_bug() );
	 * } );
	 *
	 * @param string $event   The event to observe.
	 * @param mixed  $command The command to execute. Either a Closure or a callable.
	 * @param mixed  $args    The arguments to pass to the command.
	 *
	 * @return void
	 */
	public static function listen( string $event = 'caught', $command, $args = null ) {
		$hook = array();
		if ( $command instanceof \Closure ) {
			$hook[] = array( $command );
		} elseif ( is_string( $command ) && is_callable( $command ) ) {
			$hook = array( $command );
			if ( null !== $args ) {
				$hook[] = $args;
			}
		}
		if ( ! isset( self::$listeners[ $event ] ) ) {
			self::$listeners[ $event ] = array();
		}
		self::$listeners[ $event ][] = $hook;
	}

	/**
	 * Execute event handlers.
	 *
	 * @param string $event The event to trigger.
	 *
	 * @return void
	 */
	protected static function do( string $event ) {}

	/**
	 * Handles exceptions.
	 * that might be warnings thrown by file access functions.
	 *
	 * @param int    $errno      The level of the error raised.
	 * @param string $errstr     The error message.
	 * @param string $errfile    The error file name.
	 * @param int    $errline    The error line number.
	 */
	public static function error_handler( $errno, $errstr, $errfile, $errline ){
		if ( E_WARNING === $errno ) {
			// Make it more serious than a warning so it can be caught.
			trigger_error( $errstr, E_ERROR );
			self::$caught[] = new \Exception( $errstr, $errno, );
			return true;
		} else {
			// Fall back to default php error handler.
			return false;
		}
	}

	/**
	 * Wrap PHP functions that can result in warnings, since try-catch does not work on them.
	 * Many file system functions return false on failure but also emit an E_WARNING message.
	 *
	 * Example usage:
	 *
	 *
	 * @param string $command The command to execute.
	 * @param mixed  $args    A single function parameter value or an indexed array of them.
	 *
	 * @return mixed
	 */
	protected function trap( string $command, $args = null ) {
		// Throw warnings if encountered.
		set_error_handler( array( $this, 'handle_warnings' ) );
		// Set the default result.
		$result = false;
		try {
			// code that might result in a E_WARNING.
			if ( null !== $args && ! is_array( $args ) ) {
				$result = $command( $args );
			} else {
				$result = null === $args ? $command() : $command( ...$args );
			}
		} catch ( \Exception $e ) {
			// The E_WARNING was changed to E_ERROR so it would work with try-catch.
			$result = $e;
		} finally {
			restore_error_handler();
		}
		return $result;
	}

	/**
	 * Stop catching bugs.
	 *
	 * @return bool
	 */
	public static function close() {
		if ( $this->open ) {
			$this->handle_errors = false;
			restore_error_handler();
			restore_exception_handler();
			return true;
		}
		return false;
	}
}