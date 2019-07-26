<div class="news-grid gold-ghostweave">
	<div class="max-width post-connector">
		<h2>Gold &amp; Blue</h2>
		<?php
			// WP REST API
			$response = wp_remote_get('https://www.stmarytx.edu/wp-json/wp/v2/posts?categories=178&per_page=7&school=58');
			if(!is_wp_error($response) && $response['response']['code'] == 200) {
				$remote_posts = json_decode($response['body']);
				// display
				$i=0;
				// randomize
				shuffle($remote_posts);
				foreach($remote_posts as $remote_post) {
					if($i==0 || $i==1) {
						echo '<a class="post-item" href="' . $remote_post->link . '" style="background-image:url(\'' . $remote_post->fimg_url . '\');">';
						// "cover" tag id 791, "exclusive" tag id 792
						if(in_array(791, $remote_post->tags)) { ?>
							<div class="news-overlay">Cover Story</div>
						<?php } elseif(in_array(792, $remote_post->tags)) { ?>
							<div class="news-overlay">Web Exclusive</div>
						<?php }
						echo '<div class="overlay"><h3>' . $remote_post->title->rendered . '</h3></div></a>';
					}
					$i++;
				}
			}
		?>
		<a class="ghost-btn" href="https://www.stmarytx.edu/magazine/">Magazine</a>
		<div class="home-divider"><span class="home-line"></span></div>
		<h2>News</h2>
		<?php
			// WP REST API
			$response = wp_remote_get('https://www.stmarytx.edu/wp-json/wp/v2/posts?categories=1&per_page=4&school=58');
			if(!is_wp_error($response) && $response['response']['code'] == 200) {
				$spotlight_posts = json_decode($response['body']);
				$x=0;
				// display latest 4
				foreach($spotlight_posts as $remote_post) {
					if($x<4) {
						if($remote_post->fimg_url) {
							$imageUrl = $remote_post->fimg_url;
						} else {
							$imageUrl = 'https://law.stmarytx.edu/wp-content/uploads/2018/11/law-icon.png';
						}
						$newsdate = $remote_post->date;
						if(date('M', strtotime($newsdate)) == 'May') {
							$displaydate = date('M j', strtotime($newsdate));
						} else {
							$displaydate = date('M. j', strtotime($newsdate));
						}
						echo '<a class="post-item" href="' . $remote_post->link . '" style="background-image:url(\'' . $remote_post->fimg_url . '\');">
							<div class="news-overlay">' . $displaydate . '</div>
							<div class="overlay"><h3>' . $remote_post->title->rendered . '</h3></div>
						</a>';
						}
					$x++;
				}
			}
		?>
		<a class="ghost-btn" href="https://www.stmarytx.edu/news/">More News</a>
	</div>
</div>