<?php
/**
 * This trait simplifies including computed attributes in a Class.
 * These methods can only be invoked within object context.
 *
 * @author  Zachary K. Watkins, zwatkins.it@gmail.com
 */

namespace ThoughtfulWeb\Tools\Traits;

trait HasAttributes {

	/**
	 * The attributes allowed for the object.
	 */
	protected $allowedAttributes = array();

	/**
	 * The allowed attributes available for the object.
	 *
	 * @var array
	 */
	protected $attributes = array();

	/**
	 * Reroute attempts to access unset, protected attributes from a public context by executing
	 * the class's method named as a getter for the property. This facilitates
	 * simplicity by calculating unset properties automatically when needed.
	 *
	 * @param string $key  The property key being retrieved.
	 * @param array  $args Optional. Parameters to use when retrieving the property.
	 *
	 * @return mixed
	 */
	public function __get( string $key )
	{
		if ( ! $key ) return;

		if ( $this->isAttributeSet( $key ) ) {
			return $this->attributes[ $key ];
		}

		if ( $this->isAttributeAllowed( $key ) && $this->hasAttributeComputed( $key ) ) {
			return $this->getAttributeComputed( $key );
		}
	}

	/**
	 * Handle attempts to access unset and calculable properties by running the class method which calculates them.
	 *
	 * @param string $key   The property name.
	 * @param mixed  $value Optional. The values used to calculate the property.
	 *
	 * @return mixed
	 */
	public function __set( string $key, $value )
	{
		if ( $this->isAttributeAllowed( $key ) ) {
			if ( $this->hasAttributeComputed( $key ) ) {
				$this->setAttributeComputed( $key );
			} elseif ( ! is_null( $value ) ) {
				$this->attributes[ $key ] = $value;
			}
		}
	}

	/**
	 * Whether the attribute is assigned yet.
	 *
	 * @param  string  $key
	 * @return boolean
	 * @author Zachary K. Watkins <zwatkins.it@gmail.com>
	 */
	protected function isAttributeSet( string $key )
	{
		$value = array_key_exists( $key, $this->attributes ) ? true : false;
		return $value;
	}

	/**
	 * Whether the attribute is allowed.
	 *
	 * @param  string  $key
	 * @return boolean
	 * @author Zachary K. Watkins <zwatkins.it@gmail.com>
	 */
	protected function isAttributeAllowed( string $key )
	{
		$value = in_array( $key, $this->allowedAttributes, true ) ? true : false;
		return $value;
	}

	/**
	 * Determines if the attribute should be computed.
	 * To avoid recomputing attributes each time they are accessed, assign them to the object's
	 * attributes property.
	 *
	 * @param  string  $key
	 * @return boolean
	 * @author Zachary K. Watkins <zwatkins.it@gmail.com>
	 */
	protected function hasAttributeComputed( string $key )
	{
		$value = method_exists($this, $key) ? true : false;
		return $value;
	}

    /**
     * Set a given attribute on the class.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return $this
     */
    protected function setAttributeComputed( string $key )
	{
        $this->attributes[ $key ] = $this->$key();
	}

    /**
     * Get the value of an attribute using its mutator.
     *
     * @param  string $key The attribute key.
     * @return mixed
	 * @author Zachary K. Watkins <zwatkins.it@gmail.com>
     */
	public function getAttributeComputed( string $key )
	{
		$this->setAttributeComputed( $key );
		return $this->attributes[ $key ];
	}
}