<?php
require_once dirname( __DIR__ ) . '/Traits/FactoryMethods.php';
require_once dirname( __DIR__ ) . '/FactoryModel.php';
$a = new \ThoughtfulWeb\Tools\FactoryModel( 'NISSAN' );
$select = \ThoughtfulWeb\Tools\FactoryModel::select( 'NISSAN' );
function eval_object_property( $select, $name ) {
	$stats = [
		'class' => get_class( $select ),
		'property_exists' => property_exists( $select, $name ),
		'method_exists' => method_exists( $select, "get_$name" ),
	];
	if ( $stats['property_exists'] ) {
		$prop = new ReflectionProperty(get_class($select), $name);
		$stats['static'] = $prop->isStatic();
		$stats['protected'] = $prop->isProtected();
		$stats['public'] = $prop->isPublic();
	}
	return $stats;
}
function test_property( $select, $name ) {
	$class = get_class( $select );
	$stats = eval_object_property( $select, $name );
	echo "Testing $class->$name";
	if ( $stats['property_exists'] ) {
		echo PHP_EOL;
		print_r($stats);
		if ( $stats['static'] ) {
			echo " = {$class}::\${$name}\"";
		} else {
			echo " = {$class}->{$name}\"";
		}
		echo $stats['static'] . PHP_EOL;
		// echo '$select->' . $name . ' = (' . gettype( $value ) . ') ' . $value . PHP_EOL;
	} else {
		echo " = null (not found)\n";
	}
	$value = $select->$name;
}
print_r( $select );
test_property( $select, 'asdf' );
test_property( $select, 'uid' );
test_property( $select, 'email' );
test_property( $select, 'phone' );
test_property( $select, 'is_foo' );
test_property( $select, 'has_bar' );