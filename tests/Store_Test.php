<?php

use Awethemes\Relationships\Storage;

class Store_Test extends WP_UnitTestCase {
	/* @var \Awethemes\Relationships\Storage */
	protected $storage;

	function setUp() {
		parent::setUp();

		$storage = new Storage( 'awebooking_' );
		$storage->register_tables();

		$this->storage = $storage;
		$this->storage->install();
	}

	public function testTablesExists() {
		global $wpdb;

		$this->assertEquals( 'awebooking_', $this->storage->get_prefix() );
		$this->assertEquals( 'awebooking_relationships', $this->storage->get_table_name() );
		$this->assertEquals( 'awebooking_relationshipmeta', $this->storage->get_meta_table_name() );

		$this->assertContains( 'awebooking_relationshipmeta', $wpdb->tables );
		$this->assertObjectHasAttribute( 'awebooking_relationship', $wpdb );
	}

	public function testCreate() {
		$id = $this->storage->create( 'demo', 1, 2 );
		$data = $this->getRelation( $id );

		$this->assertTrue( $id > 0 );
		$this->assertEquals( 'demo', $data->type );
		$this->assertEquals( 1, $data->rel_from );
		$this->assertEquals( 2, $data->rel_to );

		$id = $this->storage->create( 'demo', 10, 20, 'to' );
		$data = $this->getRelation( $id );

		$this->assertTrue( $id > 0 );
		$this->assertEquals( 'demo', $data->type );
		$this->assertEquals( 20, $data->rel_from );
		$this->assertEquals( 10, $data->rel_to );
	}

	public function testGet() {
		$id = $this->storage->create( 'demo', 1, 2 );

		$data = (array) $this->getRelation( $id );
		$assertData = $this->storage->get( $id );

		$this->assertEquals( $data, $assertData );
		$this->assertNull( $this->storage->get( 0 ) );
	}

	public function testDelete() {
		$id1 = $this->insertRelation( 1, 100 );
		$id2 = $this->insertRelation( 1, 200 );

		$this->storage->delete( $id1 );
		$this->assertNull( $this->getRelation( $id1 ) );

		$this->storage->delete( [ $id2 ] );
		$this->assertNull( $this->getRelation( $id2 ) );
	}

	public function testCount() {
		$id1  = $this->storage->create( 'demo', 1, 10 );
		$id11 = $this->storage->create( 'demo', 1, 100 );
		$id2  = $this->storage->create( 'demo', 2, 20 );
		$id3  = $this->storage->create( 'demo', 3, 30 );

		$this->assertEquals( 4, $this->storage->count( 'demo' ) );

		$this->assertEquals( 2, $this->storage->count( 'demo', [
			'from' => 1,
		] ) );

		$this->assertEquals( 1, $this->storage->count( 'demo', [
			'from' => 1,
			'to'   => 10
		] ) );

		$this->assertEquals( 1, $this->storage->count( 'demo', [
			'from' => 1,
			'to'   => [10, 20]
		] ) );

		$this->assertEquals( 2, $this->storage->count( 'demo', [
			'from' => 1,
			'to'   => [10, 100, 1000] // Only have 10 and 100.
		] ) );

		$this->assertEquals( 4, $this->storage->count( 'demo', [
			'from' => [1, 2, 3],
		] ) );

		$this->assertEquals( 2, $this->storage->count( 'demo', [
			'from' => [1, 2, 3],
			'to' => [10, 20],
		] ) );
	}

	protected function getRelation($id) {
		global $wpdb;
		return $wpdb->get_row( "SELECT * FROM `{$wpdb->prefix}{$this->storage->get_table_name()}` WHERE `id` = {$id}" );
	}

	protected function insertRelation($from, $to) {
		global $wpdb;

		$wpdb->query( "INSERT INTO `{$wpdb->prefix}{$this->storage->get_table_name()}` (`rel_from`, `rel_to`, `type`) VALUES ('{$from}', '{$from}', 'demo');" );

		$this->assertTrue( $wpdb->insert_id > 0 );

		return $wpdb->insert_id;
	}
}