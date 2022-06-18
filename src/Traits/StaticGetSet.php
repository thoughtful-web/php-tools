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

trait CanGetSetStatic {
	/**
	 * Store the static property value in this class's properties.
	 *
	 * @throws \InvalidArgumentException Ensure the class property exists and the value provided is an acceptable type.
	 *
	 * @param string $prop    The property name to store the value on.
	 * @param array  ...$args A value to set or append, or a key to use for the next argument which is considered a value.
	 *
	 * @return void
	 */
	protected static function set( string $prop, ...$args ) {
		if ( ! property_exists( __CLASS__, $prop ) ) {
			throw new \InvalidArgumentException( 'The ' . __CLASS__ . " class does not have the property {$prop}" );
		}
		$arg_length = count( $args );
		if ( 0 === $arg_length ) {
			throw new \InvalidArgumentException( 'The ' . __CLASS__ . "::set() method was not passed a key and/or value to set for {$prop}" );
		}
		$ref = new \ReflectionProperty( __CLASS__, $prop );
		if ( ! $ref->isStatic() ) {
			throw new \InvalidArgumentException( 'Trying to access non-static property ' . __CLASS__ . "->\${$prop} with static method " . __CLASS__ . '::' . __FUNCTION__ . '(). Either define your property using "static $' . $prop . '" or use the non-static method ' . __CLASS__ . '->set().' );
		}
		$cvalue = self::$$prop;
		$type   = gettype( $cvalue );
		$key    = null;
		$value  = $args[0];
		if ( 1 < $arg_length ) {
			$key   = $args[0];
			$value = $args[1];
		}
		// Set the value.
		if ( 'array' !== $type ) {
			self::$$prop = $value;
		} elseif ( array_keys( $cvalue ) !== range( 0, count( $cvalue ) - 1 ) ) {
			// Associative array assigned to property.
			if ( null !== $key ) {
				self::$$prop[ $key ] = $value;
			} else {
				throw new \InvalidArgumentException( 'The ' . __CLASS__ . "::set() method is setting a value for array property \"{$prop}\" without providing a key." );
			}
		} elseif ( null === $key ) {
			self::$$prop[] = $value;
		} else {
			self::$$prop[ $key ] = $value;
		}
	}

	/**
	 * Get the static property value.
	 *
	 * @throws \InvalidArgumentException Ensure correct parameters are passed to the method.
	 *
	 * @param string $prop The property name to retrieve.
	 * @param mixed  $key  Optional. A key for the property being retrieved if an array.
	 *
	 * @return mixed
	 */
	protected static function get( string $prop, $key = null ) {
		if ( ! property_exists( __CLASS__, $prop ) ) {
			throw new \InvalidArgumentException( 'The ' . __CLASS__ . " class does not have the property \"{$prop}\"" );
		}
		$ref = new \ReflectionProperty( __CLASS__, $prop );
		if ( ! $ref->isStatic() ) {
			throw new \InvalidArgumentException( 'Trying to access non-static property ' . __CLASS__ . "->\${$prop} with static method " . __CLASS__ . '::' . __FUNCTION__ . '(). Either define your property using "static $' . $prop . '" or use the non-static method ' . __CLASS__ . '->get().' );
		}
		$cvalue = self::$$prop;
		$type   = gettype( $cvalue );
		if ( 'array' !== $type || null === $key ) {
			return self::$$prop;
		} elseif ( ! array_key_exists( $key, $cvalue ) ) {
			throw new \InvalidArgumentException( 'The ' . __CLASS__ . "::get() method was passed a non-existent key of \"{$key}\" for array property \"{$prop}\"." );
		}
		return self::$$prop[ $key ];
	}
}