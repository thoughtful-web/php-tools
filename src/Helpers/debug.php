<?php

function dev_string_from_mixed( $data ) {
	$sfm = '';
	if ( is_string( $data ) ) {
		if ( 0 === strpos( $data, 'C:' ) ) {
			$sfm .= "'" . dev_shorten_path( $data ) . "'";
		} else {
			$sfm .= "'{$data}'";
		}
	} elseif ( is_array( $data ) ) {
		$strarr = '';
		if ( array_key_exists( 0, $data ) ) {
			$strarr = '[';
			// Convert to a single-line array.
			foreach ( $data as $key => $item ) {
				if ( $key > 0 ) {
					$strarr .= ', ';
				}
				$strarr .= dev_string_from_mixed( $item );
			}
			$strarr .= ']';
		} else {
			// An associative array.
			$strarr = print_r( $data, true );
		}
		$sfm .= $strarr;
	} elseif ( is_bool( $data ) ) {
		$sfm .= $data ? 'true' : 'false';
	} elseif ( is_int( $data ) ) {
		$sfm .= (string) $data;
	} elseif ( is_object( $data ) ) {
		$sfm = get_class( $data ) . ' => ' . serialize( $data );
	} else {
		$sfm = '(';
		$sfm .= gettype( $data );
		$sfm .= ') ';
		$sfm .= strval( $data );
	}
	return $sfm;
}

function dev_shorten_path( $path ) {
	$s   = DIRECTORY_SEPARATOR;
	$sep = "{$s}app{$s}public{$s}";
	$rel = explode( $sep, $path )[1];
	$rel = str_replace( "wp-content{$s}themes{$s}", '', $rel );
	return "..{$s}{$rel}";
}

function dev_format_trace( $trace ) {
	$rel  = $trace['file'];
	$rel = str_replace( dirname( __FILE__, 2 ), '', $trace['file'] );
	$rel = preg_replace( '/^.*\bapp\b/', '', $rel );
	$msg = '';
	$msg .= "Call: {$trace['function']}( ";
	foreach ( $trace['args'] as $key => $arg ) {
		if ( $key > 0 ) {
			$msg .= ', ';
		}
		$msg .= dev_string_from_mixed( $arg );
	}
	$msg .= " )\n";
	$msg .= "From: {$rel}:{$trace['line']} => {$trace['file']}:{$trace['line']}";
	return $msg;
}

function dev_error_log( $args = '', $limit = 0 ) {
	$traces = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, $limit );
	$msg  = __FUNCTION__ . '( ';
	$msg .= dev_string_from_mixed( $args );
	$msg .= " )\n";
	foreach ( $traces as $key => $trace ) {
		$msg .= ($key + 1) . " =============================\n";
		$msg .= dev_format_trace( $trace ) . "\n";
	}
	error_log( $msg );
}