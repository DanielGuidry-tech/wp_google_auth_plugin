<?php
/**
 * Class SampleTest
 *
 * @package Wpmudev_Plugin_Test
 */

/**
 * Sample test case.
 */
require '../app/admin-pages/class-googleaut-settings.php';

class SampleTest extends WP_UnitTestCase {

	/**
	 * A single example test.
	 */
	public function setup() {
		parent::setup();
	}

	public function tearDown() {
		parent::tearDown();
	}

	public function test_sample() {
		// Replace this with some actual testing code.
		$this->assertTrue( true );
	}

	public function test_scan_posts_functionality() {
        $post_id_1 = $this->factory->post->create([
            'post_title' => 'Test Post 1',
            'post_content' => 'This is the content of Test Post 1.',
            'post_type' => 'post',
            'post_status' => 'publish',
        ]);

        $post_id_1 = $this->factory->post->create([
            'post_title' => 'Test Post 1',
            'post_content' => 'This is the content of Test Post 1.',
            'post_type' => 'post',
            'post_status' => 'publish',
        ]);

        AUth::wpmudev_scan_posts();
        $posts = get_posts([
            'post_type' => 'post',
        ]);

        $this->assertEquals(2, count($posts), 'Expected two posts.');
        foreach ($posts as $post) {
            $this->assertNotEmpty($post->post_title);
            $this->assertNotEmpty($post->post_content);
            $this->assertEquals('publish', $post->post_status);

            $meta_value = get_post_meta($$post->post_id, 'wpmudev_test_last_scan', true);
            $this->assertNotEmpty($meta_value);
        }
    }
}
