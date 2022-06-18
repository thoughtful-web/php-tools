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

trait CanGetSet {
	/**
	 * Store the value in this class's properties.
	 * Can handle static and non-static properties.
	 *
	 * @throws \InvalidArgumentException Ensure the class property exists and the value provided is an acceptable type.
	 *
	 * @param string $prop    The property name to store the value on.
	 * @param array  ...$args A value to set or append, or a key to use for the next argument which is considered a value.
	 *
	 * @return void
	 */
	protected function set( string $prop, ...$args ) {
		if ( ! property_exists( __CLASS__, $prop ) ) {
			throw new \InvalidArgumentException( 'The ' . __CLASS__ . " class does not have the property {$prop}" );
		}
		$arg_length = count( $args );
		if ( 0 === $arg_length ) {
			throw new \InvalidArgumentException( 'The ' . __CLASS__ . "::set() method was not passed a key and/or value to set for {$prop}" );
		}
		$ref    = new \ReflectionProperty( __CLASS__, $prop );
		$cvalue = $ref->isStatic() ? self::$$prop : $this->$prop;
		$type   = gettype( $cvalue );
		$key    = 1 === $arg_length ? null : $args[0];
		$value  = 1 === $arg_length ? $args[0] : $args[1];
		// Set the value.
		if ( 'array' !== $type ) {
			if ( $ref->isStatic() ) {
				self::$$prop = $value;
			} else {
				$this->$prop = $value;
			}
		} elseif ( array_keys( $cvalue ) !== range( 0, count( $cvalue ) - 1 ) ) {
			// Associative array assigned to property.
			if ( null !== $key ) {
				if ( $ref->isStatic() ) {
					self::$$prop[ $key ] = $value;
				} else {
					$this->$prop[ $key ] = $value;
				}
			} else {
				throw new \InvalidArgumentException( 'The ' . __CLASS__ . "::set() method is setting a value for associative array \"{$prop}\" without providing a key." );
			}
		} elseif ( null === $key ) {
			if ( $ref->isStatic() ) {
				self::$$prop[] = $value;
			} else {
				$this->$prop[] = $value;
			}
		} else {
			if ( $ref->isStatic() ) {
				self::$$prop[ $key ] = $value;
			} else {
				$this->$prop[ $key ] = $value;
			}
		}
	}

	/**
	 * Get the property value.
	 * Can handle static and non-static properties.
	 *
	 * @throws \InvalidArgumentException Ensure correct parameters are passed to the method.
	 *
	 * @param string $prop The property name to retrieve.
	 * @param mixed  $key  Optional. A key for the property being retrieved if an array.
	 *
	 * @return mixed
	 */
	protected function get( string $prop, $key = null ) {
		if ( ! property_exists( __CLASS__, $prop ) ) {
			throw new \InvalidArgumentException( 'The ' . __CLASS__ . " class does not have the property \"{$prop}\"" );
		}
		// Handle a static or non-static property.
		$ref = new \ReflectionProperty( __CLASS__, $prop );
		if ( ! $ref->isStatic() ) {
			$cvalue = $this->$prop;
			$type   = gettype( $cvalue );
			if ( 'array' !== $type || null === $key ) {
				return $this->$prop;
			} elseif ( ! array_key_exists( $key, $cvalue ) ) {
				return null;
			}
			return $this->$prop[ $key ];
		} else {
			$cvalue = self::$$prop;
			$type   = gettype( $cvalue );
			if ( 'array' !== $type || null === $key ) {
				return self::$$prop;
			} elseif ( ! array_key_exists( $key, $cvalue ) ) {
				return null;
			}
			return self::$$prop[ $key ];
		}
	}
}