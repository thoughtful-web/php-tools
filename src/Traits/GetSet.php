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
 * Adds get and set methods to a class and handles static and object properties intuitively.
 */
trait GetSet {
	/**
	 * Store the value in this class's properties.
	 * Can handle static and non-static properties.
	 *
	 * @throws \ArgumentCountError Indicate that there weren't enough arguments passed to the
	 *                             method, since the method will fail at a point where the error
	 *                             message is somewhat ambiguous as to the root cause of the error.
	 *
	 * @param string $prop    The property name to store the value on.
	 * @param array  ...$args A value to set or append, or a key to use for the next argument which is considered a value.
	 *
	 * @return void
	 */
	protected function set( string $prop, ...$args ) {
		$arg_length = count( $args );
		if ( 0 === $arg_length ) {
			throw new \ArgumentCountError( 'Not enough arguments passed to ' . __CLASS__ . '->' . __FUNCTION__ . "(\"{$prop}\")." );
		}
		// Determine if the property is static or not.
		$ref       = new \ReflectionProperty( __CLASS__, $prop );
		$is_static = $ref->isStatic();
		unset( $ref );
		// Determine the key and value.
		$key   = 1 === $arg_length ? null : $args[0];
		$value = 1 === $arg_length ? $args[0] : $args[1];
		if ( null === $key ) {
			if ( $is_static ) {
				self::$$prop = $value;
			} else {
				$this->$prop = $value;
			}
		} else {
			if ( $is_static ) {
				self::$$prop[ $key ] = $value;
			} else {
				$this->$prop[ $key ] = $value;
			}
		}
	}

	/**
	 * Get the property value using one or more keys.
	 * Can handle static and non-static properties.
	 *
	 * @param string        $prop The property name to retrieve.
	 * @param int|string ...$keys Optional. One or more keys to retrieve from an array property.
	 *
	 * @return mixed
	 */
	protected function get( string $prop, ...$keys ) {
		// Handle a static or non-static property.
		$ref       = new \ReflectionProperty( __CLASS__, $prop );
		$is_static = $ref->isStatic();
		unset( $ref );
		// Determine the key or keys.
		$key_length = count( $keys );
		if ( 0 === $key_length ) {
			return $is_static ? self::$$prop : $this->$prop;
		}
		return $is_static ? array_fill_keys( $keys, self::$$prop ) : array_fill_keys( $keys, $this->$prop );
	}
}