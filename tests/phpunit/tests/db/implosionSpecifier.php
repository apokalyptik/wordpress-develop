<?php

/**
 * Test WPDB implosion specifier logic branch
 *
 * @group  wpdb
 * @covers wpdb::_prepare_implode
 */
class Tests_DB_PrepareImplode extends WP_UnitTestCase {

	/**
	 * Test that various types of input passed to `wpdb::_prepare_implode()` are handled correctly.
	 *
	 * @dataProvider data_prepare_implode_handling
	 *
	 * @param mixed  $input      The input to escape.
	 * @param string $expected   The expected function output.
	 * @param bool   $done_wrong Whether or not we expect to have a doing_it_wrong generated
	 */
	public function test_prepare_implode_handling( $input, $expected, $done_wrong ) {
		global $wpdb;
		$this->assertSame( $expected, $wpdb->_prepare_implode( ...$input ) );
		if ( $done_wrong ) {
			$this->setExpectedIncorrectUsage( 'wpdb::_prepare_implode' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @var array
	 */
	public function data_prepare_implode_handling() {
		return array(
			array(
				'input'    => array( '%d,%,d,%d', 0, [ 1, 2, 3 ], 4 ),
				'expected' => array( '%d,%d,%d,%d,%d', [ 0, 1, 2, 3, 4 ] ),
				null,
			),
			array(
				array( '%,f', [ 1.1, 2.2, 3.3 ] ),
				array( '%f,%f,%f', [ 1.1, 2.2, 3.3 ] ),
				null,
			),
			array(
				array( '%,s', [ 'foo', 'bar', 'baz' ] ),
				array( '%s,%s,%s', [ 'foo', 'bar', 'baz' ] ),
				null,
			),
			array(
				array( 'foo%,dbar', [ 1, 2, 3 ] ),
				array( 'foo%d,%d,%dbar', [ 1, 2, 3 ] ),
				null,
			),
			array(
				array( 'foo%%,dbar' ),
				array( 'foo%%,dbar', [] ),
				null,
			),
			array(
				array( 'f%do%fo%sb', 1, 2, 3 ),
				array( 'f%do%fo%sb', [ 1, 2, 3 ] ),
				null,
			),
			array(
				array( '%s %,d %s', 'foo', [ 1, 2, 3 ], 'bar' ),
				array( '%s %d,%d,%d %s', [ 'foo', 1, 2, 3, 'bar' ] ),
				null,
			),
			array(
				array( '%s %,d %s', 'foo', 1, 'bar' ),
				array( false, false, 'attempt to implode a non array value [1]' ),
				true,
			),
			array(
				array( '%d %3$d %,d %1$d %d', 1, [ 97, 98, 99 ], 2, null, null ),
				array( '%d %7$d %d,%d,%d %6$d %d', [ 1, 97, 98, 99, 2, 1, 2 ] ),
				null,
			),
			array(
				[ '%d %1$d %%% %', 1, true, true, true ],
				[ '%d %4$d %%% %', [ 1, true, true, 1 ] ],
				null,
			),

		);
	}

	/**
	 * Test that various types of input passed to `wpdb::prepare()
	 * but also handled by wpdb::_prepare_implode()` are handled correctly.
	 *
	 * @dataProvider data_prepare_handling
	 *
	 * @param mixed  $input      The input to escape.
	 * @param string $expected   The expected function output.
	 * @param bool   $done_wrong Whether or not we expect to have a doing_it_wrong generated
	 */
	public function test_prepare_handling( $input, $expected, $done_wrong_prepare, $done_wrong_implode ) {
		global $wpdb;

		$this->assertSame( $expected, $wpdb->prepare( ...$input ) );
		if ( $done_wrong_implode ) {
			$this->setExpectedIncorrectUsage( 'wpdb::_prepare_implode' );
		}
		if ( $done_wrong_prepare ) {
			$this->setExpectedIncorrectUsage( 'wpdb::prepare' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @var array
	 */
	public function data_prepare_handling() {
		global $wpdb;
		return array(
			array(
				'input'              => [ '%d,%,d,%d', [0, [ 1, 2, 3 ], 4 ] ],
				'expected'           => '0,1,2,3,4',
				'done_wrong_prepare' => false,
				'done_wrong_implode' => false,
			),
			array(
				[ 'IN(%,d)', [ 1, 2, 3 ] ],
				'IN(1,2,3)',
				null,
				null,
			),
			array(
				[ '`a` = %s AND `b` IN(%,s) AND `c` = %d', "foo'bar", [ 'a', "b'c", 'd' ], 9 ],
				"`a` = 'foo\\'bar' AND `b` IN('a','b\\'c','d') AND `c` = 9",
				null,
				null,
			),
			array(
				[ '%d %1$d %%% %', 1, true ],
				'1 1 ' . $wpdb->placeholder_escape() . $wpdb->placeholder_escape() . ' ' . $wpdb->placeholder_escape(),
				null,
				null,
			),
			array(
				[ "%s '%1\$s'", [ 'hello', true ] ],
				"'hello' 'hello'",
				null,
				null,
			),
			array(
				[ '%1$d %%% % %%1$d%% %%%1$d%%', 1, true ],
				"1 {$wpdb->placeholder_escape()}{$wpdb->placeholder_escape()} {$wpdb->placeholder_escape()} {$wpdb->placeholder_escape()}1\$d{$wpdb->placeholder_escape()} {$wpdb->placeholder_escape()}1{$wpdb->placeholder_escape()}",
				null,
				null,
			),
			array(
				[ '%5s', 'foo' ],
				'  foo',
				null,
				null,
			),
			array(
				[ '%\'#5s', 'foo' ],
				'##foo',
				null,
				null,
			),
			array(
				[ "'%'%%s%s", 'hello' ],
				"'{$wpdb->placeholder_escape()}'{$wpdb->placeholder_escape()}s'hello'",
				null,
				null,
			),
			array(
				[ "SELECT * FROM {$wpdb->postmeta} WHERE meta_key = %s  AND meta_value = ' {$wpdb->placeholder_escape()}s '", array( 'foo', 'bar' ) ],
				null,
				true,
				null,
			),
		);
	}



}
