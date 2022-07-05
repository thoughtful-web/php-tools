<?php
/**
 * A Model class.
 * All attributes which are computed must implement a protected method of the same name without method arguments / parameters.
 *
 * @package ThoughtfulWeb\Tools
 * @author  Zachary K. Watkins <zwatkins.it@gmail.com>
 * @license MIT https://spdx.org/licenses/MIT.html
 */

namespace ThoughtfulWeb\Tools;

use \ThoughtfulWeb\Tools\Traits\HasAttributes;

/**
 * An abstract model class which I believe accommodates most use cases for Model classes.
 *
 * @author    Zachary K. Watkins <zwatkins.it@gmail.com>
 * @copyright MIT https://spdx.org/licenses/MIT.html
 */
abstract class Model {
	use HasAttributes;
}
