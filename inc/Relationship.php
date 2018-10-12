<?php
namespace Awethemes\Relationships;

use Awethemes\Relationships\Side\Side;
use Awethemes\Relationships\Direction\Direction;

class Relationship {
	/* Constants */
	const DIRECTION_TO   = 'to';
	const DIRECTION_ANY  = 'any';
	const DIRECTION_FROM = 'from';

	const ONE_TO_ONE     = 'one-to-one';
	const ONE_TO_MANY    = 'one-to-many';
	const MANY_TO_ONE    = 'many-to-one';
	const MANY_TO_MANY   = 'many-to-many';

	/**
	 * The relationship name.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Store the direction strategy.
	 *
	 * @var \Awethemes\Relationships\Direction\Direction
	 */
	protected $direction;

	/**
	 * The "from side" object instance.
	 *
	 * @var \Awethemes\Relationships\Side\Side
	 */
	protected $from;

	/**
	 * The "to side" object instance.
	 *
	 * @var \Awethemes\Relationships\Side\Side
	 */
	protected $to;

	/**
	 * The relationship options.
	 *
	 * @var array
	 */
	protected $options = [];

	/**
	 * The storage instance.
	 *
	 * @var \Awethemes\Relationships\Manager
	 */
	protected $manager;

	/**
	 * An array of valid directions.
	 *
	 * @var array
	 */
	public static $valid_directions = [
		self::DIRECTION_TO,
		self::DIRECTION_ANY,
		self::DIRECTION_FROM,
	];

	/**
	 * An array of default options.
	 *
	 * @var array
	 */
	public static $default_options = [
		'cardinality'           => 'many-to-many',
		'reciprocal'            => false,
		'self_connections'      => false,
		'duplicate_connections' => false,
	];

	/**
	 * Constructor.
	 *
	 * @param string                                       $name      The relationship name.
	 * @param \Awethemes\Relationships\Direction\Direction $direction The direction implementation.
	 * @param \Awethemes\Relationships\Side\Side           $from      The from side instance.
	 * @param \Awethemes\Relationships\Side\Side           $to        The to side instance.
	 * @param array                                        $options   The relationship options.
	 */
	public function __construct( $name, Direction $direction, Side $from, Side $to, $options = [] ) {
		$this->name      = $name;
		$this->direction = $direction;
		$this->from      = $from;
		$this->to        = $to;
		$this->options   = $options;
		$this->parse_cardinality( $this->options['cardinality'] );
	}

	/**
	 * //
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Returns the relationships manager instance.
	 *
	 * @return \Awethemes\Relationships\Manager
	 */
	public function get_manager() {
		return $this->manager;
	}

	/**
	 * Sets the relationships manager.
	 *
	 * @param  \Awethemes\Relationships\Manager $manager The relationships manager.
	 * @return $this
	 */
	public function set_manager( Manager $manager ) {
		$this->manager = $manager;

		return $this;
	}

	/**
	 * Gets the side instance.
	 *
	 * @param string $which Which side: to or from.
	 *
	 * @return \Awethemes\Relationships\Side\Side
	 */
	public function get_side( $which ) {
		return 'to' === $which ? $this->to : $this->from;
	}

	/**
	 * Determines if the relationship allow self connections.
	 *
	 * @return bool
	 */
	public function allow_self_connections() {
		return (bool) $this->options['self_connections'];
	}

	/**
	 * Determines if the relationship allow duplicate connections.
	 *
	 * @return bool
	 */
	public function allow_duplicate_connections() {
		return (bool) $this->options['duplicate_connections'];
	}

	/**
	 * Get relationship object type.
	 *
	 * @param string $side Only "from" or "to".
	 *
	 * @return string
	 */
	public function get_object_type( $side ) {
		return ( 'from' === $side )
			? $this->from->get_object_type()
			: $this->to->get_object_type();
	}

	/**
	 * Check if the relationship has an object type on either side.
	 *
	 * @param mixed $type The object type.
	 *
	 * @return bool
	 */
	public function has_object_type( $type ) {
		return in_array( $type, [ $this->get_object_type( 'from' ), $this->get_object_type( 'to' ) ] );
	}

	/**
	 * Find direction from given object.
	 *
	 * @param  mixed $object The object or ID.
	 * @return string|null
	 */
	public function find_direction( $object ) {
		foreach ( [ 'from', 'to' ] as $direction ) {
			if ( $object_id = $this->get_side( $direction )->parse_object_id( $object ) ) {
				return $this->direction->choose_direction( $direction );
			}
		}

		return null;
	}

	/**
	 * Resolve the direction.
	 *
	 * @param  string $direction The direction.
	 * @return \Awethemes\Relationships\Direction\Directed
	 *
	 * @throws \OutOfBoundsException
	 */
	public function get_direction( $direction = 'from' /* self::DIRECTION_FROM */ ) {
		if ( ! in_array( $direction, static::$valid_directions ) ) {
			throw new \OutOfBoundsException( 'Invalid direction. The direction must be one of: ' . implode( ', ', static::$valid_directions ) . '.' );
		}

		$class = $this->direction->get_directed_class();

		return new $class( $this, $direction );
	}

	/**
	 * Returns the inverse (to) direction.
	 *
	 * @return \Awethemes\Relationships\Direction\Directed
	 */
	public function inverse() {
		return $this->get_direction( static::DIRECTION_TO );
	}

	/**
	 * Get the describe string.
	 *
	 * @return string
	 */
	public function get_describe() {
		return sprintf( '%s %s %s', $this->from->get_label(), $this->direction->get_arrow(), $this->to->get_label() );
	}

	/**
	 * Sets the cardinality on both sides.
	 *
	 * @param string $cardinality The cardinality string.
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function parse_cardinality( $cardinality ) {
		if ( preg_match( '/^(one|many)-to-(one|many)$/i', $cardinality, $matches ) ) {
			$this->from->set_cardinality( $matches[1] );
			$this->to->set_cardinality( $matches[2] );
			return;
		}

		throw new \InvalidArgumentException( 'Invalid cardinality' );
	}

	/**
	 * Add metadata for the specified object.
	 *
	 * @see add_metadata()
	 *
	 * @param int    $object_id  ID of the object metadata is for.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
	 * @param bool   $unique     Optional, default is false.
	 * @return int|false The meta ID on success, false on failure.
	 */
	public function add_meta( $object_id, $meta_key, $meta_value, $unique = false ) {
		return add_metadata( 'p2p_relationship', $object_id, $meta_key, $meta_value, $unique );
	}

	/**
	 * Retrieve metadata for the specified object.
	 *
	 * @see get_metadata()
	 *
	 * @param int    $object_id ID of the object metadata is for.
	 * @param string $meta_key  Optional. Metadata key.
	 * @param bool   $single    Optional, default is false.
	 *
	 * @return mixed
	 */
	public function get_meta( $object_id, $meta_key = '', $single = false ) {
		return get_metadata( 'p2p_relationship', $object_id, $meta_key, $single );
	}

	/**
	 * Update metadata for the specified object.
	 *
	 * If no value already exists for the specified object
	 * ID and metadata key, the metadata will be added.
	 *
	 * @see update_metadata()
	 *
	 * @param int    $object_id  ID of the object metadata is for.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
	 *
	 * @return int|bool
	 */
	public function update_meta( $object_id, $meta_key, $meta_value ) {
		return update_metadata( 'p2p_relationship', $object_id, $meta_key, $meta_value );
	}

	/**
	 * Delete metadata for the specified object.
	 *
	 * @see delete_metadata()
	 *
	 * @param int    $object_id  ID of the object metadata is for.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Optional. Metadata value.
	 * @param bool   $delete_all Optional, default is false.
	 *
	 * @return bool
	 */
	public function delete_meta( $object_id, $meta_key, $meta_value = '', $delete_all = false ) {
		return delete_metadata( 'p2p_relationship', $object_id, $meta_key, $meta_value, $delete_all );
	}

	/**
	 * Handle call dynamic methods from directed.
	 *
	 * @param string $name      The method name.
	 * @param array  $arguments The method arguments.
	 * @return mixed
	 */
	public function __call( $name, $arguments ) {
		return $this->get_direction()->{$name}( ...$arguments );
	}
}
