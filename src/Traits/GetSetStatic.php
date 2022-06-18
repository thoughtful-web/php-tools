<?php
/**
 * A PHP trait that provides intuitive getters and setters.
 *
 * @author    Zachary K. Watkins, zwatkins.it@gmail.com
 * @copyright Zachary K. Watkins, 2022
 * @package   ThoughtfulWeb\Tools
 * @see       http://github.com/thoughtful-web/tools/
 * @license   MIT
 */

namespace ThoughtfulWeb\Tools\Traits;

/**
 * Adds static get and set methods to a class, which means they can only get and set static properties.
 */
trait GetSetStatic {
	/**
	 * Store the static property value in this class's properties.
	 *
	 * @throws \ArgumentCountError Indicate that there weren't enough arguments passed to the
	 *                             method, since the method will fail at a point where the error
	 *                             message is somewhat ambiguous as to the root cause of the error.
	 *
	 * @param string $prop    The property name to store the value on.
	 * @param array  ...$args A value to set or append, or a key to use for the next argument which
	 *                        is considered a value.
	 *
	 * @return void
	 */
	protected static function set( string $prop, ...$args ) {
		$arg_length = count( $args );
		if ( 0 === $arg_length ) {
			throw new \ArgumentCountError( 'Not enough arguments passed to ' . __CLASS__ . '::' . __FUNCTION__ . "(\"{$prop}\")." );
		}
		$key   = 1 === $arg_length ? null : $args[0];
		$value = 1 === $arg_length ? $args[0] : $args[1];
		if ( null === $key ) {
			self::$$prop = $value;
		} else {
			self::$$prop[ $key ] = $value;
		}
	}

	/**
	 * Get the static property value.
	 *
	 * @param string $prop The property name to retrieve.
	 * @param mixed  $key  Optional. A key for the property being retrieved if an array.
	 *
	 * @return mixed
	 */
	protected static function get( string $prop, ...$keys ) {
		$key_length = count( $keys );
		if ( 0 === $key_length ) {
			return self::$$prop;
		}
		return array_fill_keys( $keys, self::$$prop );
	}
}