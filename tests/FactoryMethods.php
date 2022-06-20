<?php
/**
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
 */
echo memory_get_usage() . ' Before defining class Model' . PHP_EOL;
class Model {
	use GetSet;
	protected $is_foo;
	protected $uid;
	public function __construct( int $uid ){
		$this->uid = $uid;
		self::$created[ $uid ] = $this;
	}
	public function is_foo() {
		if ( null !== $this->is_foo ) return $this->is_foo;
		// First time.
		$this->is_foo = true;
		return $this->is_foo;
	}
}
/**
 * This trait is designed to simplify how a Class gets, sets, and stores calculated properties. Calculated properties can be expensive and require redundant code to handle detecting existing values, calculating non-existent values, and returning the latest calculated value.
 */
echo memory_get_usage() . ' Before defining trait GetSet' . PHP_EOL;
trait GetSet {
	protected static $created = [];
    public static function __callStatic($name, $arguments)
    {
		echo __LINE__ . PHP_EOL;
		// $uid = array_shift( $arguments );
		// echo gettype($uid) . PHP_EOL . PHP_EOL;
		// return self::$created[ $uid ];
        // Note: value of $name is case sensitive.
        echo "Calling static method '$name' "
             . implode(', ', $arguments). "\n";
    }
    public function __call($name, $arguments)
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
		$results = [];
		foreach ( $uids as $key ) {
			if ( isset( self::$created[ $key ] ) ) {
				$results[ $key ] = self::$created[ $key ];
			} else {
				$results[ $key ] = null;
			}
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
		echo 'Printing args' . PHP_EOL;
		print_r($args);
		$args = $args[0];
		foreach ( $args as $key => $value ) {
			if ( isset( self::$created[ $key ] ) ) {
				$results[ $key ] = self::$created[ $key ];
			} elseif ( is_array( $value ) ) {
				echo $key . PHP_EOL;
				print_r($value);
				print_r(...$value);
				$results[ $key ] = new static( ...$value );
			} else {
				$results[ $key ] = new static( $value );
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
echo memory_get_usage() . ' Before new Model( 1 )' . PHP_EOL;
$uid = 1;
$a = new Model( $uid );
echo memory_get_usage() . ' $a = new Model( ' . $uid . ' )' . PHP_EOL;
$uid = 2;
$b = new Model( $uid );
echo memory_get_usage() . ' $b = new Model( ' . $uid . ' )' . PHP_EOL;
$created = Model::select( 1 );
echo memory_get_usage() . ' $created = Model::select( 1 )' . PHP_EOL;
print_r( $created );
echo memory_get_usage() . ' Model::select( 1 )->is_foo() = ';
echo Model::select( 1 )->is_foo() ? 'true' . PHP_EOL : 'false' . PHP_EOL;
$created = Model::selectOrCreate( 3 );
echo memory_get_usage() . ' $created = Model::selectOrCreate( 3 )' . PHP_EOL;
print_r( $created ) . PHP_EOL;
$created = Model::selectOrCreate( [3 => [3], 2 => [2]] );
echo memory_get_usage() . ' $created = Model::selectOrCreate( 3 )' . PHP_EOL;
print_r( $created ) . PHP_EOL;
echo "Model::get( 'created', ['1', 'a', 'd'] );" . PHP_EOL;
Model::get( 'created', [1, 'is_foo'] );
echo "Model::get( 'created', '1', '2' );" . PHP_EOL;
Model::get( 'created', '1', '2' );
?>
