<?php
/**
 * A PHP trait that provides intuitive getters and setters.
 *
 * What we're trying to achieve is the following:
 *
 * In a static context, this is what happens:
 *
 * Class::foo() runs Trait::get( 'foo' )
 *   1. Trait::get( 'foo' ) returns
 *      a. Class::$foo if it is defined.
 *      b. null if not defined.
 *   2. Class::foo() returns result if not null
 *   3. else, $value = (custom logic)
 *   4. Class::foo() uses Trait::set( 'foo', $value ) to store $value
 *   5. return Class::$foo
 *
 * Class::foo( 'bar' ) runs Trait::get( 'foo', 'bar' )
 *   1. Trait::get( 'foo', 'bar' ) returns
 *      a. Class::$foo['bar'] if it is defined.
 *      b. null if not defined.
 *   2. Class::foo( 'bar' ) returns result if not null
 *   3. else, $value = (custom logic)
 *   4. Class::foo( 'bar' ) uses Trait::set( 'foo', 'bar', $value ) to store $value
 *   5. return Class::$foo['bar']
 *
 * In an object context, this is what happens:
 *
 * Class->foo() runs Class::foo() runs Trait::get( 'foo' )
 *   1. Trait::get( 'foo' ) returns
 *      a. Class->foo if it is defined.
 *      b. null if not defined.
 *   2. Class->foo() returns result if not null
 *   3. else, $value = (custom logic)
 *   4. Class->foo() uses Trait::set( 'foo', $value ) to store $value
 *   5. return Class->foo
 *
 * Class->foo( 'bar' ) runs Class::foo() runs Trait::get( 'foo', 'bar' )
 *   1. Trait::get( 'foo', 'bar' ) returns
 *      a. Class->foo['bar'] if it is defined.
 *      b. null if not defined.
 *   2. Class->foo( 'bar' ) returns result if not null
 *   3. else, $value = (custom logic)
 *   4. Class->foo( 'bar' ) uses Trait::set( 'foo', 'bar', $value ) to store $value
 *   5. return Class->foo['bar']
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
trait GetSetFetch {
	public function __call( string $name, array $arguments ) {
		echo $name . PHP_EOL;
		if ( property_exists( __CLASS__, $name ) ) {
			self::$name( $arguments );
		}
	}
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
		$value  = $args[ $arg_length - 1 ];
		$key    = 1 === $arg_length ? null : $args[0];
		$subkey = 3 === $arg_length ? $args[1] : null;
		if ( null === $key ) {
			self::$$prop = $value;
		} elseif ( null === $subkey ) {
			self::$$prop[ $key ] = $value;
		} else {
			self::$$prop[ $key ][ $subkey ] = $value;
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
		} elseif ( 1 === $key_length ) {
			return self::$$prop[ $keys[0] ];
		} else {
			return self::$$prop[ $keys[0] ][ $keys[1] ];
		}
	}
}