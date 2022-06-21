<?php
/**
 * A Trait which facilitates model creation and forwards calls to defined but unset properties to their same-name class methods if defined.
 * Must declare such properties with calculated values as protected.
 * @package ThoughtfulWeb\Tools\Traits\FactoryMethods
 */
/**
 * This trait is designed to simplify how a Class gets, sets, and stores calculated properties. Calculated properties can be expensive and require redundant code to handle detecting existing values, calculating non-existent values, and returning the latest calculated value.
 *
 * The high-level goal is to create class methods as an interface for class properties.
 * This allows us to:
 * 1. Instantiate objects while only defining properties which are immediately needed or calculable
 *    without expensive operations like database queries.
 * 2. Calculate dynamic properties when the property is needed for the first time and is not set.
 * 3. To do all of this succinctly, automatically, and also intuitively.
 *
 * What we want to happen:
 *   Call "Model::createdOrCreate( ...$constructor_args )->is_foo()" returns Class::$created[ $uid ]->is_foo()
 *   Class::$created stores all Class objects created.
 * The benefit is that we don't have to remember a variable name and worry about variable name
 * collision. We only have to use keys that are intuitive based on what Class the Trait is applied
 * to.
 *
 * What we want to happen:
 *   Class::is_foo( $uid ) returns Class::$created[ $uid ]->is_foo()
 *   Class::$created stores all Class objects created.
 * The benefit is that we don't have to remember a variable name and worry about variable name
 * collision. We only have to use keys that are intuitive based on what Class the Trait is applied
 * to.
 *
 * Class::get( $uid, 'is_foo' ):
 * 1. Trait::get( $uid, 'is_foo' )
 *    a. If set returns Trait::$created[ $uid ]['is_foo']
 *    b. Else if Trait::$created[ $uid ] is set return Trait::$created[ $uid ]->is_foo()
 *    c. Else return Trait::make( $uid )->is_foo()
 * $this->is_foo():
 * 1. Returns $this->is_foo if set.
 * 2. Else:
 *    a. Runs (custom code)
 *    b. Sets $this->is_foo to $value
 *    c. Returns $value
 *
 * @author Zachary K. Watkins, zwatkins.it@gmail.com
 * @package ThoughtfulWeb\Tools
 * @see https://www.php.net/manual/en/language.oop5.traits.php
 */

namespace ThoughtfulWeb\Tools\Traits;
trait FactoryMethods {
	protected static $cache = [];

	/**
	 * Reroute attempts to access unset, protected properties from a public context by executing
	 * the class's non-static method named as a getter for the property. This facilitates
	 * simplicity by calculating uncalculated properties automatically.
	 *
	 * @param string $key The property key being retrieved.
	 *
	 * @return mixed
	 */
	public function __get( string $key ) {
		if ( property_exists( $this, $key ) ) {
			if ( isset( $this->$key ) ) {
				echo ' = ' . get_class($this) . '->' . $key;
				return $this->$key;
			} else {
				$key = 'get_' . $key;
				if ( method_exists( $this, $key ) ) {
					echo ' = ' . get_class($this) . '->' . $key . '()';
					return $this->$key();
				}
			}
		}
	}
	/**
	 * Handle attempts to access unset and calculable properties by running the class method which calculates them.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	public function __set($key, $value) {
		echo ' [setting ' . $key . ' to ' . $value . '] ';
		if ( property_exists( $this, $key ) ) {
			if ( isset( $this->$key ) ) {
				return $this->$key;
			} else {
				$key = 'get_' . $key;
				if ( method_exists( $this, $key ) ) {
					return $this->$key();
				}
			}
		}
	}

	/**
	 * Magic method callStatic.
	 *
	 * @param [type] $name
	 * @param [type] $arguments
	 * @return void
	 */
    public static function __callStatic( $name, $arguments )
    {
		echo __LINE__ . PHP_EOL;
		// $uid = array_shift( $arguments );
		// echo gettype($uid) . PHP_EOL . PHP_EOL;
		// return self::$created[ $uid ];
        // Note: value of $name is case sensitive.
        echo "Calling static method '$name' "
             . implode(', ', $arguments). "\n";
    }
    public function __call( $name, $arguments )
    {
		echo __LINE__ . PHP_EOL;
        // Note: value of $name is case sensitive.
        echo "Calling object method '$name' "
             . implode(', ', $arguments). "\n";
    }

	/**
	 * Select a model from the static collection.
	 *
	 * @param mixed[] ...$uids The unique IDs for each Class object to retrieve.
	 *
	 * @return object
	 */
	public static function select( ...$uids ) {
		$cache_key = static::$_cache ?? 'cache';
		$results = [];
		foreach ( $uids as $key ) {
			$results[ $key ] = self::$$cache_key[ $key ] ?? null;
		}
		return count( $uids ) === 1 ? array_shift( $results ) : $results;
	}

	/**
	 * Select a model from the static collection or create a new one using the provided parameters.
	 *
	 * Select or create a model with a unique ID of 'uid' and no constructor parameters:
	 * Model::selectOrCreate( 'uid' );
	 *
	 * Select or create a model with a unique ID of 'uid' and custructor parameters:
	 * Model::selectOrCreate(['uid' => ['parameter_1']]);
	 *
	 * @param mixed[] ...$args {
	 *     The arrays of arguments used to construct a new Class object if not set. This is an
	 *     associative array where the array keys are $uid values and the values are constructor
	 *     parameters.
	 *     @key array ...$params The key is the Model's UID and the value is constructor parameters.
	 * }
	 *
	 * @return object
	 */
	public static function selectOrCreate( ...$args ) {
		$results = [];
		$args = $args[0];
		foreach ( $args as $key => $value ) {
			if ( isset( self::$created[ $key ] ) ) {
				$results[ $key ] = self::$created[ $key ];
			} else {
				if ( is_array( $value ) ) {
					$results[ $key ] = new static( ...$value );
				} else {
					$results[ $key ] = new static( $value );
				}
				self::$created[ $key ] = $results[ $key ];
			}
		}
		return count( $args ) === 1 ? array_shift( $results ) : $results;
	}

	/**
	 * Look up a (possibly) nested property value and if not found run an associated function to fill it with.
	 *
	 * @param string $prop     The class property to retrieve.
	 * @param mixed  ...$items The arguments to use when retrieving values.
	 * @return mixed
	 */
	public static function get( $prop, ...$items ) {
		// Interpret the arguments.
		$arg_length = count( $items );
		$defined    = array();
		$undefined  = array();
		if ( $arg_length === 0 ) {
			return self::$$prop ?? null;
		} else {
			foreach ( $items as $item ) {
				if ( is_array( $item ) ) {
					$value = self::array_index_recursive( $item, self::$$prop );
				} else {
					$value = self::$$prop[ $item ] ?? null;
				}
				if ( null !== $value ) {
					$defined[] = $value;
				} else {
					$undefined[] = [ $prop, $item ];
				}
			}
		}
		if ( $undefined ) {
			/**
			 * Create the requested items for the first time.
			 * Steps:
			 * 1. Examine the nature of the property targeted.
			 *    If a class method exists, run the class method.
			 */
			// Then set them on the class.
			// Then add them to the $defined array.
			echo 'Undefined: ' . serialize( $undefined ) . PHP_EOL;
		}
		return $arg_length === 1 ? $defined[0] : $defined;
	}

	protected static function set( string $prop, ...$args ) {
		self::$$prop = $args[0];
	}

	/**
	 * Search recursively through an array for a single value using the list of keys.
	 *
	 * @see http://php.net/manual/en/function.array-values.php
	 *
	 * @param mixed[] $keys  The array keys to use to select a value.
	 * @param array   $array The array to search.
	 *
	 * @return mixed
	 */
	protected static function array_index_recursive( $keys, $array ) {
		if ( ! count( $keys ) || ! is_array( $array ) || ! array_key_exists( $keys[0], $array ) ) return null;
		$nextKey = array_shift( $keys );
		if ( count( $keys ) > 0 ) {
			return self::array_index_recursive( $keys, $array[ $nextKey ] );
		} else {
			return $array[ $nextKey ];
		}
	}
}