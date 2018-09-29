<?php
namespace Awethemes\Relationships;

use Database\Query\Builder;
use Awethemes\Database\Database;

class Storage {
	/**
	 * Init the WP hooks.
	 *
	 * Call this method before the "init" hook.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_tables' ], 0 );
		add_filter( 'wpmu_drop_tables', [ $this, 'wpmu_drop_tables' ] );
		add_action( 'deleted_post', [ $this, 'delete_relationship_objects' ] );
	}

	/**
	 * Perform create the tables.
	 *
	 * @return void
	 */
	public function install() {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$collate = '';
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		dbDelta("
CREATE TABLE {$wpdb->prefix}p2p_relationships (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rel_from BIGINT UNSIGNED NOT NULL,
  rel_to BIGINT UNSIGNED NOT NULL,
  type VARCHAR(42) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY type (type),
  KEY rel_from (rel_from),
  KEY rel_to (rel_to)
) $collate;
CREATE TABLE {$wpdb->prefix}p2p_relationshipmeta (
  meta_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  p2p_relationship_id BIGINT UNSIGNED NOT NULL,
  meta_key VARCHAR(191) default NULL,
  meta_value longtext NULL,
  PRIMARY KEY (meta_id),
  KEY p2p_relationship_id (p2p_relationship_id),
  KEY meta_key (meta_key(32))
) $collate;");
	}

	/**
	 * Perform drop the tables.
	 *
	 * @return void
	 */
	public function uninstall() {
		global $wpdb;

		// @codingStandardsIgnoreStart
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}p2p_relationships" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}p2p_relationshipmeta" );
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Register the tables into the $wpdb.
	 *
	 * @access private
	 */
	public function register_tables() {
		global $wpdb;

		$wpdb->tables[] = 'p2p_relationships';
		$wpdb->p2p_relationships = $wpdb->prefix . 'p2p_relationships';

		$wpdb->tables[] = 'p2p_relationshipmeta';
		$wpdb->p2p_relationshipmeta = $wpdb->prefix . 'p2p_relationshipmeta';
	}

	/**
	 * Uninstall tables when MU blog is deleted.
	 *
	 * @access private
	 *
	 * @param  array $tables List the tables to be deleted.
	 * @return array
	 */
	public function wpmu_drop_tables( $tables ) {
		global $wpdb;

		$tables[] = $wpdb->prefix . 'p2p_relationships';
		$tables[] = $wpdb->prefix . 'p2p_relationshipmeta';

		return $tables;
	}

	/**
	 * Perform delete relationship objects.
	 *
	 * @return void
	 */
	public function delete_relationship_objects() {
		// TODO: ...
		$object_type = str_replace( 'deleted_', '', current_action() );
	}

	/**
	 * Add a relationship for two objects.
	 *
	 * @param string    $type      The relationship type.
	 * @param int|mixed $from      The "from" object ID.
	 * @param int|mixed $to        The "to" object ID.
	 * @param string    $direction The direction: "from" or "to".
	 *
	 * @return bool|int
	 */
	public function create( $type, $from, $to, $direction = Relationship::DIRECTION_FROM ) {
		$dirs = array_filter(
			array_map( [ Utils::class, 'parse_object_id' ], [ $from, $to ] )
		);

		if ( count( $dirs ) !== 2 ) {
			return false;
		}

		if ( Relationship::DIRECTION_TO === $direction ) {
			$dirs = array_reverse( $dirs );
		}

		return $this->new_query()->insertGetId( [
			'type'     => $type,
			'rel_from' => $dirs[0],
			'rel_to'   => $dirs[1],
		] );
	}

	/**
	 * Delete a relationship by given IDs.
	 *
	 * @param  string|array $ids The relationship ids.
	 * @return int|false
	 */
	public function delete( $ids ) {
		$ids = wp_parse_id_list( $ids );

		if ( empty( $ids ) ) {
			return false;
		}

		$deleted = $this->new_query()->whereIn( 'id', $ids )->delete();

		if ( $deleted > 0 ) {
			$this->new_query( 'p2p_relationshipmeta' )->whereIn( 'p2p_relationship_id', $ids )->delete();

			return $deleted;
		}

		return false;
	}

	/**
	 * Retrieve a single connection by ID.
	 *
	 * @param  int $id The connection ID.
	 * @return array|null
	 */
	public function get( $id ) {
		return $this->new_query()->find( $id ) ?: null;
	}

	/**
	 * Returns first connection in a relationship.
	 *
	 * @param string $type The relationship name.
	 * @param array  $args The query args.
	 *
	 * @return array|null
	 */
	public function first( $type, $args = [] ) {
		return $this->new_connection_query( $type, $args )->first();
	}

	/**
	 * Returns connections in a relationship.
	 *
	 * @param  string $type The relationship name.
	 * @param  array  $args The query args.
	 * @return array
	 */
	public function find( $type, $args = [] ) {
		return $this->new_connection_query( $type, $args )->get();
	}

	/**
	 * Returns number of connections in a relationship.
	 *
	 * @param  string $type The relationship name.
	 * @param  array  $args The query args.
	 * @return int
	 */
	public function count( $type, $args = [] ) {
		return $this->new_connection_query( $type, $args )->count();
	}

	/**
	 * Return new query builder of a table.
	 *
	 * @param  string|null $table The table name.
	 * @return \Awethemes\Database\Builder
	 */
	public function new_query( $table = 'p2p_relationships' ) {
		return $table ? Database::table( $table ) : Database::newQuery();
	}

	/**
	 * Create new query to retrieve connections.
	 *
	 * @param string $type The relationship name.
	 * @param array  $args The query args.
	 *
	 * @return \Database\Query\Builder
	 */
	public function new_connection_query( $type, $args = [] ) {
		$args = wp_parse_args( $args, [
			'from'      => '*',
			'to'        => '*',
			'limit'     => -1,
			'direction' => Relationship::DIRECTION_FROM,
		] );

		// Begin the the query builder.
		$query = $this->new_query()->where( 'type', '=', $type );

		// Filter values for the relation conditions.
		$vars = array_map( function ( $value ) {
			if ( is_null( $value ) || in_array( $value, [ 'any', '*' ] ) ) {
				return null;
			}

			return wp_parse_id_list( $value ) ?: null;
		}, Utils::array_only( $args, [ 'from', 'to' ] ) );

		if ( $vars['from'] || $vars['to'] ) {
			$directions = Utils::expand_direction( $args['direction'] );

			$query->where( function ( Builder $query ) use ( $directions, $vars ) {
				foreach ( $directions as $direction ) {
					$_vars = array_values( $vars );

					if ( Relationship::DIRECTION_TO === $direction ) {
						$_vars = array_reverse( $_vars );
					}

					$this->apply_relation_conditions( $query, $_vars[0], $_vars[1] );
				}
			} );
		}

		return $query->limit( $args['limit'] );
	}

	/**
	 * Apply the relation where conditions.
	 *
	 * @param \Database\Query\Builder $builder The query builder instance.
	 * @param array|null              $from    The from items.
	 * @param array|null              $to      The to items.
	 */
	protected function apply_relation_conditions( Builder $builder, $from, $to ) {
		$vars = array_filter( compact( 'from', 'to' ) );

		$builder->orWhere( function ( Builder $query ) use ( $vars ) {
			foreach ( $vars as $key => $value ) {
				$query->whereIn( "rel_{$key}", wp_parse_id_list( $value ) );
			}
		} );
	}
}
