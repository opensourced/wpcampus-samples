<?php
// Post Connector shortcode
add_shortcode('stmu_posts', 'stmu_posts_connector');
function stmu_posts_connector($atts, $content = null) {
	$a = shortcode_atts(array(
		'category' => '',
		'number' => '0',
		'tag' => '',
		'school' => '',
		'images' => 'no'
	), $atts);
	$number = $a['number'];
	$postArgs = array(
		'post_type' => 'post',
		'posts_per_page' => $number,
	);
	if($a['images'] == 'yes') {
		$postArgs['meta_key'] = '_thumbnail_id'; // only grabs posts with a featured image
	}
	if(!empty($a['category'])) {
		$postArgs['category_name'] = $a['category'];
	}
	if(!empty($a['tag'])) {
		$postArgs['tag'] = $a['tag'];
	}
	if(!empty($a['school'])) {
		$terms = 'school-' . $a['school'];
		$postArgs['tax_query'] = array(
			array(
				'taxonomy' => 'school',
				'field' => 'slug',
				'terms' => array("$terms"), // only grabs posts in this school
			)
		);
	}
	// Exclude current post from results
	global $post;
	$postArgs['post__not_in'] = array($post->ID);
	$postQuery = new WP_Query($postArgs);
	if($postQuery->have_posts()) {
		// With images
		if($a['images'] == 'yes') {
			$stmu_posts = '<div class="post-connector">';
			while($postQuery->have_posts()) {
				$postQuery->the_post();
				$stmu_posts .= '<a class="post-item" href="' . get_the_permalink() . '" style="background-image:url(\'';
				$stmu_posts .= get_the_post_thumbnail_url(get_the_id(), 'spotlight') . '\');" />';
				$stmu_posts .= '<div class="overlay">' . get_the_title() . '</div>';
				$stmu_posts .= '</a>';
			}
			$stmu_posts .= '</div>';
		// List without images
		} else {
			$stmu_posts = '<ul class="stmu_posts">';
			while($postQuery->have_posts()) {
				$postQuery->the_post();
				$stmu_posts .= '<li><a href="' . get_the_permalink() . '">' . get_the_title() . '</a></li>';
			}
			$stmu_posts .= '</ul>';
		}
		// Always return to be displayed
		return $stmu_posts;
	}
}
?>