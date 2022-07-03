<?php
/**
 * Example Model class using Traits.
 * This class ensures Model information is handled in a centralized location and is cached in memory upon creation in a way that is available to all scripts without using global variables.
 *
 * @package ThoughtfulWeb\Tools
 * @author  Zachary K. Watkins, zwatkins.it@gmail.com
 */

namespace ThoughtfulWeb\Tools;

class FactoryModel {
	use \ThoughtfulWeb\Tools\Traits\FactoryMethods;

	/**
	 * The name of this class's static property which stores the model cache.
	 *
	 * @var string
	 */
	protected static $_cache = 'models';

	/**
	 * Store models as they are retrieved from the database or created in memory.
	 *
	 * @var array
	 */
	protected static $models = array();

	/**
	 * The unique ID.
	 *
	 * @var string
	 */
	public $uid;

	/**
	 * The email address.
	 *
	 * @var string
	 */
	protected $email = 'hello@gmail.com';

	/**
	 * The phone number.
	 *
	 * @var string
	 */
	protected $phone;

	/**
	 * If the Model is foo.
	 *
	 * @var bool
	 */
	public $is_foo;

	/**
	 * If the Model has bar.
	 *
	 * @var bool
	 */
	public $has_bar;

	/**
	 * Store the default information.
	 *
	 * @var array
	 */
	public static $defaults = array(
		'email' => 'info@test.com',
		'phone' => '(555) 555-5555',
	);

	/**
	 * Construct an instance of the class.
	 *
	 * @return void
	 */
	public function __construct( string $uid ) {
		$this->uid = $uid;
		self::$models[ $uid ] = $this;
	}

	/**
	 * Calculate the phone number.
	 *
	 * @return void
	 */
	public function get_phone() {
		$this->phone = self::$defaults['phone'];
		return $this->phone . ' [calculated]';
	}

	/**
	 * Is the Model a foo?
	 *
	 * @return bool
	 */
	protected function get_is_foo() {
		return true;
	}
}