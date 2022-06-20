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
 *
 * The objective is to allow classes to have getters and setters available in both a static and non-static context without duplicating code.
 */
trait GetSet {
	/**
	 * This function is triggered when invoking inaccessible methods in an object context. We use
	 * it to redirect calls to static methods in an object context. This eliminates the need to
	 * duplicate methods for an object context. We only allow this for methods whose name matches
	 * an existing property.
	 *
	 * @param string $name      The name of the method being called.
	 * @param array  $arguments An enumerated array containing the parameters passed to the method.
	 *
	 * @return mixed
	 */
	public function __call( string $name, array $arguments ) {
		echo $name . PHP_EOL;
		if ( property_exists( $name ) ) {
			self::$name( $arguments );
		}
	}

	/**
	 * An interpreter for classes using this trait to convert their accessor methods to refer to the defined public static method.
	 *
	 * For example, `$this->get( 'foo' );` looks for the static class method "{$this->twsp}_foo" which is defined on the class inheriting this trait.
	 *
	 * @param string $func The function name representing an attribute to get.
	 *
	 * @return mixed
	 */
	// protected function get( string $func ) {
	// 	$key   = __FUNCTION__;
	// 	$value = $this->get( $key );
	// 	if ( is_bool( $value ) ): return $value;
	// 	else: $method = "agent_$key"; endif;
	// 	// Evaluate.
	// 	$value = self::$method( $this->agent_mlsid );
	// 	// Assign.
	// 	$this->set( $key, $value );
	// 	return $value;
	// }

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
		} elseif ( 3 < $arg_length ) {
			throw new \ArgumentCountError( 'Too many arguments passed to ' . __CLASS__ . '->' . __FUNCTION__ . "(\"{$prop}\")." );
		}
		// Determine if the property is static or not.
		$ref       = new \ReflectionProperty( __CLASS__, $prop );
		$is_static = $ref->isStatic();
		unset( $ref );
		// Determine the key and value.
		$value  = $args[ $arg_length - 1 ];
		$key    = 1 === $arg_length ? null : $args[0];
		$subkey = 3 === $arg_length ? $args[1] : null;
		if ( null === $key ) {
			if ( $is_static ) {
				self::$$prop = $value;
			} else {
				$this->$prop = $value;
			}
		} elseif ( null === $subkey ) {
			if ( $is_static ) {
				self::$$prop[ $key ] = $value;
			} else {
				$this->$prop[ $key ] = $value;
			}
		} else {
			if ( $is_static ) {
				self::$$prop[ $key ][ $subkey ] = $value;
			} else {
				$this->$prop[ $key ][ $subkey ] = $value;
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
		} elseif ( 1 === $key_length ) {
			return $is_static ? self::$$prop[ $keys[0] ] : $this->$prop[ $keys[0] ];
		} else {
			return $is_static ? self::$$prop[ $keys[0] ][ $keys[1] ] : $this->$prop[ $keys[0] ][ $keys[1] ];
		}
	}
}