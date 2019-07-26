<div class="row">
	<div class="large-4 columns">
		<a href="https://www.stmarytx.edu/category/news/"><h3 class="text-center"><?php the_field('main_happening_nothumb', 'option');  ?></h3></a>
		<?php $content = '';
		$rss = fetch_feed( 'https://www.stmarytx.edu/feed/?post_type=posts&schools=school-of-law&cat=1' );
		$maxitems = 4;
		if ( ! is_wp_error( $rss ) ) {
			$rss_items = $rss->get_items( 0, $maxitems );
		}
		for ($i=0; $i<count($rss_items); $i++ ) {
			if($i!=0){
				$item = $rss_items[$i];
				?><a href="<?php echo $item->get_permalink();?>"><div class="large-12 medium-6 columns"><div class="news-articles"><h4><?php echo $item->get_title(); ?></h4></div></div></a><?php
			}
		} ?>
	</div>
	<div class="large-8 columns">
		<a href="https://www.stmarytx.edu/category/spotlight-stories/"><h3 class="text-center"><?php the_field('main_happening_thumb', 'option'); ?></h3></a>
		<div class="text-center">
			<ul class="spotlights row small-up-1 medium-up-2">
			<?php
			$rss = fetch_feed('https://www.stmarytx.edu/feed/?post_type=posts&schools=school-of-law&cat=178','SimpleXMLElement', LIBXML_NOCDATA);
			$rss_items = $rss->get_items(0,5);
			$sortableArray = array();
			$i=0;
			foreach($rss_items as $item) {
				$sortableArray[$i][] = $item->get_title();
				$sortableArray[$i][] = $item->get_permalink();
				// Strip HTML tags and Read More out of the excerpt
				$strippedDesc = preg_replace('/<[^>]*>/', '', $item->get_description());
				$strippedDesc = preg_replace('/ ...Read More/', '', $strippedDesc);
				$strippedDesc = substr($strippedDesc, 0, 200);
				$strippedDesc = substr($strippedDesc, 0, strripos($strippedDesc, " "));
				$strippedDesc = trim(preg_replace('/\s+/', ' ', $strippedDesc));
				$sortableArray[$i][] = $strippedDesc;
				// Extract image from the spotlight's full content
				$content = $item->get_content();
				preg_match('/< *img[^>]*src *= *["\']?([^"\']*)/i', $content, $imageUrl);
				// [1] is just the image URL. [0] is most but not all of the <img> tag.
				// if the image is a resized version (ends in ###x###.jpg), grab the full-size version
				$imageUrl = $imageUrl[1];
				if(preg_match("/\\d{3}x\\d{3}\\.jpg/", $imageUrl, $matches)) {
					$sortableArray[$i][] = substr($imageUrl, 0, -12) . '.jpg';
				} else {
					$sortableArray[$i][] = $imageUrl;
				}
				$i++;
			}
			shuffle($sortableArray);
			for($i=0; $i<4; $i++) {
				echo '<li class="column"><a href="' . $sortableArray[$i][1] . '" style="text-decoration:none;"><div>';
				echo "	<div class=\"spotlight-content text-left\">";
				echo '<div class="spotlight-image" style="background-image:url(' . $sortableArray[$i][3] . ')";></div>';
				echo '<div class="spotlight-text" style="border-top:none;"><h4 style="text-decoration:none; text-align:left;">' . $sortableArray[$i][0] . '</h4></div>';
				echo '</div></div></a></li>';
			} ?>
			</ul>
		</div> 
	</div>
</div>