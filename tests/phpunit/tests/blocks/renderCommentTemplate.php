<?php
/**
 * Comment Template block rendering tests.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.0.0
 */

/**
 * Tests for the Comment Template block.
 *
 * @since 6.0.0
 *
 * @group blocks
 */
class Tests_Blocks_RenderReusableCommentTemplate extends WP_UnitTestCase {

	private static $custom_post;
	private static $comment_ids;
	private static $per_page = 5;

	public function set_up() {
		parent::set_up();

		update_option( 'page_comments', true );
		update_option( 'comments_per_page', self::$per_page );
		update_option( 'comment_order', 'ASC' );

		self::$custom_post = self::factory()->post->create_and_get(
			array(
				'post_type'    => 'dogs',
				'post_status'  => 'publish',
				'post_name'    => 'metaldog',
				'post_title'   => 'Metal Dog',
				'post_content' => 'Metal Dog content',
				'post_excerpt' => 'Metal Dog',
			)
		);

		self::$comment_ids = self::factory()->comment->create_post_comments(
			self::$custom_post->ID,
			1,
			array(
				'comment_author'       => 'Test',
				'comment_author_email' => 'test@example.org',
				'comment_author_url'   => 'http://example.com/author-url/',
				'comment_content'      => 'Hello world',
			)
		);
	}

	/**
	 * @ticket 55505
	 * @covers ::build_comment_query_vars_from_block
	 */
	function test_build_comment_query_vars_from_block_with_context() {
		$parsed_blocks = parse_blocks(
			'<!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template -->'
		);

		$block = new WP_Block(
			$parsed_blocks[0],
			array(
				'postId' => self::$custom_post->ID,
			)
		);

		$this->assertEquals(
			array(
				'orderby'       => 'comment_date_gmt',
				'order'         => 'ASC',
				'status'        => 'approve',
				'no_found_rows' => false,
				'post_id'       => self::$custom_post->ID,
				'hierarchical'  => 'threaded',
				'number'        => 5,
				'paged'         => 1,
			),
			build_comment_query_vars_from_block( $block )
		);
	}

	/**
	 * @ticket 55567
	 * @covers ::build_comment_query_vars_from_block
	 */
	function test_build_comment_query_vars_from_block_with_context_no_pagination() {
		update_option( 'page_comments', false );
		$parsed_blocks = parse_blocks(
			'<!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template -->'
		);

		$block = new WP_Block(
			$parsed_blocks[0],
			array(
				'postId' => self::$custom_post->ID,
			)
		);

		$this->assertSameSetsWithIndex(
			array(
				'orderby'       => 'comment_date_gmt',
				'order'         => 'ASC',
				'status'        => 'approve',
				'no_found_rows' => false,
				'post_id'       => self::$custom_post->ID,
				'hierarchical'  => 'threaded',
			),
			build_comment_query_vars_from_block( $block )
		);
		update_option( 'page_comments', true );
	}

	/**
	 * @ticket 55505
	 * @covers ::build_comment_query_vars_from_block
	 */
	function test_build_comment_query_vars_from_block_no_context() {
		$parsed_blocks = parse_blocks(
			'<!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template -->'
		);

		$block = new WP_Block( $parsed_blocks[0] );

		$this->assertEquals(
			array(
				'orderby'       => 'comment_date_gmt',
				'order'         => 'ASC',
				'status'        => 'approve',
				'no_found_rows' => false,
				'hierarchical'  => 'threaded',
				'number'        => 5,
				'paged'         => 1,
			),
			build_comment_query_vars_from_block( $block )
		);
	}

	/**
	 * Test that if pagination is set to display the last page by default (i.e. newest comments),
	 * the query is set to look for page 1 (rather than page 0, which would cause an error).
	 *
	 * Regression: https://github.com/WordPress/gutenberg/issues/40758.
	 *
	 * @ticket 55658
	 * @covers ::build_comment_query_vars_from_block
	 */
	function test_build_comment_query_vars_from_block_pagination_with_no_comments() {
		$comments_per_page     = get_option( 'comments_per_page' );
		$default_comments_page = get_option( 'default_comments_page' );

		update_option( 'comments_per_page', 50 );
		update_option( 'previous_default_page', 'newest' );

		$post_without_comments = self::factory()->post->create_and_get(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_name'    => 'fluffycat',
				'post_title'   => 'Fluffy Cat',
				'post_content' => 'Fluffy Cat content',
				'post_excerpt' => 'Fluffy Cat',
			)
		);

		$parsed_blocks = parse_blocks(
			'<!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template -->'
		);

		$block = new WP_Block(
			$parsed_blocks[0],
			array(
				'postId' => $post_without_comments->ID,
			)
		);

		$this->assertSameSetsWithIndex(
			array(
				'orderby'       => 'comment_date_gmt',
				'order'         => 'ASC',
				'status'        => 'approve',
				'no_found_rows' => false,
				'post_id'       => $post_without_comments->ID,
				'hierarchical'  => 'threaded',
				'number'        => 50,
			),
			build_comment_query_vars_from_block( $block )
		);

		update_option( 'comments_per_page', $comments_per_page );
		update_option( 'default_comments_page', $default_comments_page );
	}


	/**
	 * Test that both "Older Comments" and "Newer Comments" are displayed in the correct order
	 * inside the Comment Query Loop when we enable pagination on Discussion Settings.
	 * In order to do that, it should exist a query var 'cpage' set with the $comment_args['paged'] value.
	 *
	 * @ticket 55505
	 * @covers ::build_comment_query_vars_from_block
	 */
	function test_build_comment_query_vars_from_block_sets_cpage_var() {

		// This could be any number, we set a fixed one instead of a random for better performance.
		$comment_query_max_num_pages = 5;
		// We subtract 1 because we created 1 comment at the beginning.
		$post_comments_numbers = ( self::$per_page * $comment_query_max_num_pages ) - 1;
		self::factory()->comment->create_post_comments(
			self::$custom_post->ID,
			$post_comments_numbers,
			array(
				'comment_author'       => 'Test',
				'comment_author_email' => 'test@example.org',
				'comment_author_url'   => 'http://example.com/author-url/',
				'comment_content'      => 'Hello world',
			)
		);
		$parsed_blocks = parse_blocks(
			'<!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template -->'
		);

		$block  = new WP_Block(
			$parsed_blocks[0],
			array(
				'postId'           => self::$custom_post->ID,
				'comments/inherit' => true,
			)
		);
		$actual = build_comment_query_vars_from_block( $block );
		$this->assertSame( $comment_query_max_num_pages, $actual['paged'] );
		$this->assertSame( $comment_query_max_num_pages, get_query_var( 'cpage' ) );
	}

	/**
	 * Test rendering a single comment
	 *
	 * @ticket 55567
	 */
	function test_rendering_comment_template() {
		$parsed_blocks = parse_blocks(
			'<!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template -->'
		);

		$block = new WP_Block(
			$parsed_blocks[0],
			array(
				'postId' => self::$custom_post->ID,
			)
		);

		$this->assertSame(
			str_replace( array( "\n", "\t" ), '', '<ol class="wp-block-comment-template"><li id="comment-' . self::$comment_ids[0] . '" class="comment even thread-even depth-1"><div class="wp-block-comment-author-name"><a rel="external nofollow ugc" href="http://example.com/author-url/" target="_self" >Test</a></div><div class="wp-block-comment-content"><p>Hello world</p></div></li></ol>' ),
			str_replace( array( "\n", "\t" ), '', $block->render() )
		);
	}

	/**
	 * Test rendering nested comments:
	 *
	 * └─ comment 1
	 *    └─ comment 2
	 *       └─ comment 3
	 *       └─ comment 4
	 *    └─ comment 5
	 *
	 * @ticket 55567
	 */
	function test_rendering_comment_template_nested() {
		$first_level_ids = self::factory()->comment->create_post_comments(
			self::$custom_post->ID,
			2,
			array(
				'comment_parent'       => self::$comment_ids[0],
				'comment_author'       => 'Test',
				'comment_author_email' => 'test@example.org',
				'comment_author_url'   => 'http://example.com/author-url/',
				'comment_content'      => 'Hello world',
			)
		);

		$second_level_ids = self::factory()->comment->create_post_comments(
			self::$custom_post->ID,
			1,
			array(
				'comment_parent'       => $first_level_ids[0],
				'comment_author'       => 'Test',
				'comment_author_email' => 'test@example.org',
				'comment_author_url'   => 'http://example.com/author-url/',
				'comment_content'      => 'Hello world',
			)
		);

		$parsed_blocks = parse_blocks(
			'<!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template -->'
		);

		$block = new WP_Block(
			$parsed_blocks[0],
			array(
				'postId' => self::$custom_post->ID,
			)
		);

		$top_level_ids = self::$comment_ids;
		$expected      = str_replace(
			array( "\n", "\t" ),
			'',
			<<<END
				<ol class="wp-block-comment-template">
					<li id="comment-{$top_level_ids[0]}" class="comment odd alt thread-odd thread-alt depth-1">
						<div class="wp-block-comment-author-name">
							<a rel="external nofollow ugc" href="http://example.com/author-url/" target="_self" >
								Test
							</a>
						</div>
						<div class="wp-block-comment-content">
							<p>Hello world</p>
						</div>
						<ol>
							<li id="comment-{$first_level_ids[0]}" class="comment even depth-2">
								<div class="wp-block-comment-author-name">
									<a rel="external nofollow ugc" href="http://example.com/author-url/" target="_self" >
										Test
									</a>
								</div>
								<div class="wp-block-comment-content">
									<p>Hello world</p>
								</div>
								<ol>
									<li id="comment-{$second_level_ids[0]}" class="comment odd alt depth-3">
										<div class="wp-block-comment-author-name">
											<a rel="external nofollow ugc" href="http://example.com/author-url/" target="_self" >
												Test
											</a>
										</div>
										<div class="wp-block-comment-content">
											<p>Hello world</p>
										</div>
									</li>
								</ol>
							</li>
							<li id="comment-{$first_level_ids[1]}" class="comment even depth-2">
								<div class="wp-block-comment-author-name">
									<a rel="external nofollow ugc" href="http://example.com/author-url/" target="_self" >
										Test
									</a>
								</div>
								<div class="wp-block-comment-content">
									<p>Hello world</p>
								</div>
							</li>
						</ol>
					</li>
				</ol>
END
		);

		$this->assertSame(
			$expected,
			str_replace( array( "\n", "\t" ), '', $block->render() )
		);
	}

	/**
	 * Test that unapproved comments are included if it is a preview.
	 *
	 * @ticket 55634
	 * @covers ::build_comment_query_vars_from_block
	 */
	function test_build_comment_query_vars_from_block_with_comment_preview() {
		$parsed_blocks = parse_blocks(
			'<!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template -->'
		);

		$block = new WP_Block(
			$parsed_blocks[0],
			array(
				'postId' => self::$custom_post->ID,
			)
		);

		$commenter_filter = function () {
			return array(
				'comment_author_email' => 'unapproved@example.org',
			);
		};

		add_filter( 'wp_get_current_commenter', $commenter_filter );

		$this->assertEquals(
			build_comment_query_vars_from_block( $block ),
			array(
				'orderby'            => 'comment_date_gmt',
				'order'              => 'ASC',
				'status'             => 'approve',
				'no_found_rows'      => false,
				'include_unapproved' => array( 'unapproved@example.org' ),
				'post_id'            => self::$custom_post->ID,
				'hierarchical'       => 'threaded',
				'number'             => 5,
				'paged'              => 1,
			)
		);
	}
}
