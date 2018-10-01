<?php
namespace Awethemes\Relationships\Direction;

use WP_Error;
use Awethemes\Relationships\Utils;
use Awethemes\Relationships\Relationship;

class Directed {
	/**
	 * The relationship instance.
	 *
	 * @var \Awethemes\Relationships\Relationship
	 */
	protected $relationship;

	/**
	 * The direction (to|from|any).
	 *
	 * @var string
	 */
	protected $direction;

	/**
	 * The direction maps.
	 *
	 * @var array
	 */
	static protected $direction_maps = [
		'current'  => [
			'to'   => 'to',
			'from' => 'from',
			'any'  => 'from',
		],
		'opposite' => [
			'to'   => 'from',
			'from' => 'to',
			'any'  => 'to',
		],
	];

	/**
	 * Constructor.
	 *
	 * @param \Awethemes\Relationships\Relationship $relationship The relationship instance.
	 * @param string                                $direction    The direction.
	 */
	public function __construct( Relationship $relationship, $direction ) {
		Utils::assert_direction( $direction );

		$this->direction = $direction;

		$this->relationship = $relationship;
	}

	/**
	 * Returns new instance with flip direction.
	 *
	 * @return static
	 */
	public function flip() {
		$flip_direction = Relationship::DIRECTION_ANY;

		if ( Relationship::DIRECTION_ANY !== $this->direction ) {
			$flip_direction = Relationship::DIRECTION_TO === $this->direction ? Relationship::DIRECTION_FROM : Relationship::DIRECTION_TO;
		}

		return new static( $this->relationship, $flip_direction );
	}

	/**
	 * Returns the current "side".
	 *
	 * @return \Awethemes\Relationships\Side\Side
	 */
	public function get_current() {
		$side = static::$direction_maps['current'][ $this->direction ];

		return $this->relationship->get_side( $side );
	}

	/**
	 * Returns the opposite "side".
	 *
	 * @return \Awethemes\Relationships\Side\Side
	 */
	public function get_opposite() {
		$side = static::$direction_maps['opposite'][ $this->direction ];

		return $this->relationship->get_side( $side );
	}

	public function get_connected( $item ) {
		$ids = $this->get_storage()->find(
			$this->relationship->get_name(),
			[
				'from'   => $item,
				'column' => 'rel_to',
			]
		);

		return wp_list_pluck( $ids, 'rel_to' );
	}

	/**
	 * Returns connections in a relationship.
	 *
	 * @param array $args The query args.
	 * @return array|null|object
	 */
	public function find( $args = [] ) {
		return $this->get_storage()->find( $this->relationship->get_name(), $args );
	}

	/**
	 * Determines if two objects has any connections.
	 *
	 * @param mixed $from The from item.
	 * @param mixed $to   The to item.
	 *
	 * @return bool
	 */
	public function has( $from, $to ) {
		list( $from, $to ) = array_filter(
			func_get_args(), [ Utils::class, 'parse_object_id' ]
		);

		$count = $this->get_storage()->count(
			$this->relationship->get_name(),
			[
				'to'    => $to,
				'from'  => $from,
				'limit' => 1,
			]
		);

		return $count > 0;
	}

	/**
	 * Connect two items.
	 *
	 * @param mixed $from     The from item.
	 * @param mixed $to       The to item.
	 * @param array $metadata Optional. An array of metadata.
	 *
	 * @return int|\WP_Error
	 */
	public function connect( $from, $to, $metadata = [] ) {
		if ( ! $from = $this->get_current()->parse_object_id( $from ) ) {
			return new WP_Error( 'first_parameter', 'Invalid first parameter.' );
		}

		if ( ! $to = $this->get_opposite()->parse_object_id( $to ) ) {
			return new WP_Error( 'second_parameter', 'Invalid second parameter.' );
		}

		if ( $from === $to && ! $this->relationship->allow_self_connections() ) {
			return new WP_Error( 'self_connection', 'Connection between an element and itself is not allowed.' );
		}

		if ( ! $this->relationship->allow_duplicate_connections() && $this->has( $from, $to ) ) {
			return new WP_Error( 'duplicate_connection', 'Duplicate connections are not allowed.' );
		}

		/*
		if ( 'one' === $directed->get_opposite()->get_cardinality() && $this->has_connections( $from ) ) {
			return new WP_Error( 'cardinality_opposite', 'Cardinality problem (opposite).' );
		}*/

		/*if ( 'one' === $directed->get_current()->get_cardinality() ) {
			if ( $this->flip_direction()->has_connections( $to ) ) {
				return new WP_Error( 'cardinality_current', 'Cardinality problem (current).' );
			}
		}*/

		$rel_id = $this
			->get_storage()
			->create( $this->relationship->get_name(), $from, $to );

		if ( ! $rel_id ) {
			// ...
		}

		return $rel_id;
	}

	/**
	 * Disconnect two items.
	 *
	 * @param mixed $from The from item.
	 * @param mixed $to   The to item.
	 *
	 * @return bool|WP_Error Boolean or WP_Error on failure.
	 */
	public function disconnect( $from, $to ) {
		if ( ! $from = $this->get_current()->parse_object_id( $from ) ) {
			return new WP_Error( 'first_parameter', 'Invalid first parameter.' );
		}

		if ( ! $to = $this->get_opposite()->parse_object_id( $to ) ) {
			return new WP_Error( 'second_parameter', 'Invalid second parameter.' );
		}

		$delete = $this->get_storage()->first(
			$this->relationship->get_name(), compact( 'from', 'to' )
		);

		return $this->get_storage()->delete( $delete['id'] );
	}

	protected function check_objects() {
	}

	/**
	 * Gets the storage instance.
	 *
	 * @return \Awethemes\Relationships\Storage
	 */
	protected function get_storage() {
		return $this->relationship->get_manager()->get_storage();
	}
}
