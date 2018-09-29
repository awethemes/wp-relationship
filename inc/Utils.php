<?php
namespace Awethemes\Relationships;

class Utils {
	/**
	 * Get a subset of the items from the given array.
	 *
	 * @param  array        $array The array.
	 * @param  array|string $keys  The keys.
	 * @return array
	 */
	public static function array_only( $array, $keys ) {
		return array_intersect_key( $array, array_flip( (array) $keys ) );
	}

	/**
	 * Expand the "any" direction if given.
	 *
	 * @param  string $direction The direction name.
	 * @return array
	 */
	public static function expand_direction( $direction ) {
		if ( ! in_array( $direction, Relationship::$valid_directions ) ) {
			throw new \OutOfBoundsException( 'Invalid direction. The direction must be one of: ' . implode( ', ', Relationship::$valid_directions ) . '.' );
		}

		if ( Relationship::DIRECTION_ANY === $direction ) {
			return [ Relationship::DIRECTION_FROM, Relationship::DIRECTION_TO ];
		}

		return [ $direction ];
	}

	/**
	 * Parse IDs for sql.
	 *
	 * @param mixed $ids The ids.
	 * @return string
	 */
	public static function parse_sql_ids( $ids ) {
		if ( is_numeric( $ids ) ) {
			return (string) $ids;
		}

		return implode( ',', wp_parse_id_list( $ids ) );
	}

	/**
	 * Parse the object_id.
	 *
	 * @param  mixed $object The WP object.
	 * @return int|null
	 */
	public static function parse_object_id( $object ) {
		if ( is_numeric( $object ) && $object > 0 ) {
			return (int) $object;
		}

		if ( ! empty( $object->ID ) ) {
			return (int) $object->ID;
		}

		if ( ! empty( $object->term_id ) ) {
			return (int) $object->term_id;
		}

		if ( $object instanceof \Awethemes\WP_Object\WP_Object ) {
			return $object->get_id();
		}

		return 0;
	}
}
