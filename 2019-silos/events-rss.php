<?php // get calendar events
function grabEvents($feedUrl) {
	date_default_timezone_set('America/Chicago');
	$rss = fetch_feed( "$feedUrl" );
	if ( ! is_wp_error( $rss ) ) {
		$rss->enable_order_by_date(false);
		$maxitems = 5;
		$rss_items = $rss->get_items( 0, $maxitems );
		if($feedUrl == 'https://ems-app.stmarytx.edu/MasterCalendar/RSSFeeds.aspx?data=mL1noRCrG1I%2fLTuo7OuVKyJfpoYkKVl3SF6kknXitMI%3d') {
			foreach ( $rss_items as $item ) {
				$schemaDate = $item->get_date('Y-M-d');
				$eventMonth = $item->get_date('M');
				$eventDay = $item->get_date('j');
				$eventTitle = $item->get_title();
				$shortTitle = trimTitle($eventTitle);
				?>
							<li itemscope itemtype="http://schema.org/Event" class="column">
								<div class="item-box">
								<meta itemprop="startDate" content="<?php echo $schemaDate; ?>">
								<div class="cal">
									<div class="date-box">
											<span class="calMonth"><?php echo $eventMonth; ?></span>
											<span class="calDay"><?php echo $eventDay; ?></span>
									</div>
								</div>
								<a itemprop="url" href="<?php echo $item->get_permalink();?>">
									<h3 itemprop="name"><?php echo $shortTitle ?></h3>
								</a>
								</div>>
							</li>
								<?php
			}
		} else {
			foreach ( $rss_items as $item ) {
				$eventTitle = $item->get_title();
				$shortTitle = trimTitle($eventTitle);
				echo '<li itemscope itemtype="http://schema.org/Event" class="column"><div class="item-box">'
							. '<meta itemprop="startDate" content="' . $item->get_date('Y-m-d') . '">'
									. '<div class="cal"><div class="date-box"><span class="calMonth">' . $item->get_date('M') . '</span><span class="calDay"> ' . $item->get_date('j') . '</span></div></div><a itemprop="url" href="' . $item->get_permalink() . '"><h3 itemprop="name">' . $shortTitle . '</h3></a></div></li>';
							
					}
		}
	}
}

function grabAllEvents($feedUrl) {
	date_default_timezone_set('America/Chicago');
	$rss = fetch_feed( "$feedUrl" );
    $rss->enable_order_by_date(false);
	$maxitems = 99;
	if ( ! is_wp_error( $rss ) ) {
		$rss_items = $rss->get_items( 0, $maxitems );
	}
	foreach ( $rss_items as $item ) {
		$schemaDate = $item->get_date('Y-M-d');
		$eventMonth = $item->get_date('M');
		$eventDay = $item->get_date('j');
		$eventTitle = $item->get_title();
		$shortTitle = trimTitle($eventTitle);
					?><!-- from grabevents.php --><li itemscope itemtype="http://schema.org/Event" class="column"><div class="item-box"><meta itemprop="startDate" content="<?php echo $schemaDate; ?>"><div class="cal"><div class="date-box"><span class="calMonth"><?php echo $eventMonth; ?></span><span class="calDay"><?php echo $eventDay; ?></span></div></div><a itemprop="url" href="<?php echo $item->get_permalink();?>"><h3 itemprop="name"><?php echo $shortTitle ?></h3></a></div></li><?php
	}
}

// truncate calendar event titles
function trimTitle($eventTitle) {
	if(strlen($eventTitle) > 50) {
		$eventTitle = substr($eventTitle, 0, 50);
		$eventTitle = substr($eventTitle, 0, strripos($eventTitle, " "));
		$eventTitle = trim(preg_replace( '/\s+/', ' ', $eventTitle));
	}
	return $eventTitle;
}
?>