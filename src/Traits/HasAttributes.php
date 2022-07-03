<?php
/**
 * This trait simplifies including computed attributes in a Class.
 * These methods can only be invoked within object context.
 *
 * @package ThoughtfulWeb\Tools\Traits
 * @author  Zachary K. Watkins, zwatkins.it@gmail.com
 */

namespace ThoughtfulWeb\Tools\Traits;

trait ComputedAttributes {

	/**
	 * A property which contains a model's attributes.
	 *
	 * @var array
	 */
	protected $attributes = array();

	/**
	 *
	 */
	protected static $attributesComputed = array();

    /**
     * Set a given attribute on the class.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return mixed
     */
    public function setAttribute( string $key, $value )
	{

        $this->attributes[$key] = $value;

        return $this;

	}

    /**
     * Handle an attempt to retrieve an attribute.
     *
     * @param  string $key
     * @return mixed
     */
    public function getAttribute( string $key )
	{
        if ( ! $key ) return;

		if (
			array_key_exists($key, $this->attributes)
			|| $this->hasAttributeComputed($key)
			|| $this->hasAttributeUsingDatabase($key)
		) {
			return $this->getAttributeValue($key);
		}

		if (method_exists(self::class, $key)) {
			return $this->$key();
		}
	}

	public function hasAttributeComputed( string $key ) {

        if (isset(static::$attributesComputed[get_class($this)][$key])) {
            return static::$attributesComputed[get_class($this)][$key];
        }

        if (! method_exists($this, $method = $key)) {
            return static::$attributesComputed[get_class($this)][$key] = false;
        }

        $returnType = (new \ReflectionMethod($this, $method))->getReturnType();

        return static::$attributesComputed[get_class($this)][$key] = $returnType &&
				$returnType instanceof \ReflectionNamedType &&
				is_callable($this->{$method}()->get);

	}

    /**
     * Get the value of an attribute using its mutator.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function computeAttribute( string $key, $value)
    {
        return $this->{'get' . $key . 'Attribute'}( $value );
    }

	public function hasAttributeUsingDatabase( string $key ) {

	}
}