<?php
/*
 * Template Name: Events
 */
get_header();
date_default_timezone_set('America/Chicago'); ?>
	<style type="text/css">
		form.quasi-heading { max-width:94%; margin:0 auto; padding:1em 0 0; background:#ddd; border:1px solid #555; border-bottom:none; }
		form.quasi-heading select { width:auto; margin:0 .5em 1em; padding:.5em; }
		form.quasi-heading input[type="submit"] { margin:0 .5em 1em; background:#fff; box-shadow:5px 5px 0 #888; border:1px solid #888; text-transform:none; }
		body.page-template-tpl-events-php table.unstriped { max-width:94%; margin:0 auto 1.5em; }
		body.page-template-tpl-events-php table.unstriped td, body.page-template-tpl-events-php table.unstriped th { width:14.2857%; border:1px solid #555; }
		body.page-template-tpl-events-php table.unstriped th { border-bottom:none; }
		table.unstriped tbody tr { background-color:#fff; }
		.calendarContainer { font-size:0.9em; }
		.newCalDayNames { background:#555; color:#fff; border:1px solid #fff; }
		.singleDate { vertical-align:top; }
		.dayNumber { float:right; margin:0.5em 0; padding:0.1em 0.25em 0; text-align:center; line-height:1.25em; background:#ddd; border-radius:50%; box-shadow:2px 2px 0 #999; }
		.dayName { margin-top:.5em; }
		.featuredEvent { background:#ddd; padding:.5em .5em 0; box-shadow:0 0 2px #777; }
		.featuredEvent a { color:#036; }
		.featuredEvent a:hover, .featuredEvent a:focus { color:#222; }
		.singleEvent { clear:both; margin:0.5em 0; border-bottom:1px solid #ccc; line-height:1.1em; padding-bottom:.5em; }
		.singleEvent:last-child { border-bottom:none; }
		.singleEvent a { text-decoration:none; }
		.singleEvent a:hover { text-decoration:underline; }
		a.small-btn.calCurrent { background:#f2bf49; cursor:auto; }
		@media screen and (max-width:1150px) {
			body.page-template-tpl-events-php table.unstriped td, body.page-template-tpl-events-php table.unstriped th { width:100%; }
			table.stack thead { display:none; }
			.calendarContainer { font-size:1.2em; }
			.block table td.singleDate.withEvents.otherMonth, .otherMonth { display:none; }
			.dayNumber { float:left; margin-right:.5em; }
			.block table td.singleDate { display:none; }
			.block table td.singleDate.withEvents { display:block; }
			/* mobile dates are striped white/blue to make each day clearer. blue stripes need white links, except for featured events. */
			.block table td.singleDate.mobileStripe { background:#025; }
			.block table td.singleDate.mobileStripe a, .block table td.singleDate.mobileStripe .dayName { color:#fff; }
			.block table td.singleDate.mobileStripe a:hover, .block table td.singleDate.mobileStripe a:focus { color:#f2bf49; }
			.block table td.singleDate.mobileStripe .featuredEvent a { color:#036; }
			.block table td.singleDate.mobileStripe .featuredEvent a:hover, .block table td.singleDate.mobileStripe .featuredEvent a:focus { color:#222; }
		}
		@media print {
			tr { width:100%; }
			td, th { width: 14.1%; }
		}
	</style>
	<main id="theContent" data-swiftype-index="true">
		<div class="block">
			<?php // the_content, only if it exists
			if( '' !== get_post()->post_content ) {
				echo '<div class="max-width">';
				the_content();
				echo '</div>';
			}
			// reusable function that pulls raw data from Master Calendar API
			function stmu_get_mc_api_events($date = '', $calendar = 5) {
				if($date == '') {
					$startDate = new DateTime("first day of this month midnight", new DateTimeZone('America/Chicago'));
					$endDate = new DateTime("last day of this month 11:59 pm", new DateTimeZone('America/Chicago'));
				} else {
					$startDate = new DateTime("first day of $date midnight", new DateTimeZone('America/Chicago'));
					$endDate = new DateTime("last day of $date 11:59 pm", new DateTimeZone('America/Chicago'));
				}
				if($calendar == 'all') {
					$calendar = array('1','2','3','4','5','6');
				} else {
					$calendar = array($calendar);
				}
				// pull from Master Calendar API
				$url = 'https://ems-app.stmarytx.edu/MCAPI/MCAPIService.asmx?WSDL';
				$headers = get_headers($url);
				if($headers[0] == 'HTTP/1.1 200 OK') {
					$client = new SoapClient('https://ems-app.stmarytx.edu/MCAPI/MCAPIService.asmx?WSDL', array('trace' => 1));
					$params = array(
						'soap_version'	=> 'SOAP_1_2',
						'startDate'		=> $startDate->format('Y-m-d') . 'T00:00:00',
						'endDate'		=> $endDate->format('Y-m-d') . 'T23:59:59',
						'userName'		=> '', // credentials are required!
						'password'		=> '',
						'calendars'		=> $calendar
					);
					$output = array();
					$result = $client->__soapCall('GetEvents', array($params));
					if(!is_soap_fault($result)) {
						$xml = simplexml_load_string($result->GetEventsResult);
						foreach($xml->Data as $event) {
							$allDay = '';
							$priority = '';
							if($event->IsAllDayEvent == 'true') {
								$allDay = true;
							}
							if($event->Priority == 1) {
								$priority = 'high';
							}
							// Only show priority 1 and 2 events
							$duplicate='';
							if($event->Priority < 3) {
								// Loop through existing to see if there is a duplicate
								for($q=0; $q<count($output); $q++) {
									if((str_replace('"', '\"', $event->{'Title'}) == $output[$q]['title']) && (strtotime($event->TimeEventStart) == strtotime($output[$q]['start']))) {
										$duplicate=1;
									}
								}
								// If no duplicate was found, add to the array
								if($duplicate != 1) {
									$output[] = array(
										'title' => str_replace('"', '\"', $event->{'Title'}),
										'start' => $event->TimeEventStart,
										'url' => 'https://ems-app.stmarytx.edu/MasterCalendar/EventDetails.aspx?EventDetailId=' . $event->EventDetailID,
										'allDay' => $allDay,
										'priority' => $priority,
										'eventType' => $event->EventTypeName
									);
								}
							}
						}
					}
					$sort = array();
					// sort by date, since Master Calendar API does not have this capability
					foreach($output as $key => $value) {
						$sort[$key] = strtotime($value['start']);
					}
					array_multisort($sort, SORT_ASC, $output);
				} else {
					$output = 'No events';
				}
				return $output;
			}
			// default to General calendar
			if(isset($_GET['category'])) { $category = $_GET['category']; } else { $category = 5; }
			// default to current month
			if(isset($_GET['date'])) { $dateToDisplay = $_GET['date']; } else { $dateToDisplay = date('Y-m'); }
			// display the requested calendar ?>
			<div class="max-width text-center smallPad">
				<?php
				if(isset($_GET['date'])) {
					// gives date[0] year, date[1] month
					$date = explode('-', $_GET['date']);
				} else {
					$today = date('Y-m');
					$date = explode('-', $today);
				}
				// calculate next & previous month & year (year changes if month is 01 or 12)
				if($date[1] == 12) {
					$prevMonth = '11';
					$prevYear = $date[0];
					$nextMonth = '01';
					$nextYear = $date[0] + 1;
				} elseif($date[1] == 1) {
					$prevMonth = '12';
					$prevYear = $date[0] - 1;
					$nextMonth = '02';
					$nextYear = $date[0];
				} else {
					$prevMonth = $date[1] - 1;
					$prevYear = $date[0];
					$nextMonth = $date[1] + 1;
					$nextYear = $date[0];
				}
				$prevDate = $prevYear . '-' . $prevMonth;
				$nextDate = $nextYear . '-' . $nextMonth;
				?>
				<a class="small-btn<?php if($_GET['category'] == '5' || !isset($_GET['category'])) { echo ' calCurrent'; } ?>" href="?category=5<?php if(isset($_GET['date'])) { echo '&date=' . $_GET['date']; } ?>">General</a>
				<a class="small-btn" href="/academics/registrar/academic-calendars/">Academics</a>
				<a class="small-btn<?php if($_GET['category'] == '4') { echo ' calCurrent'; } ?>" href="?category=4<?php if(isset($_GET['date'])) { echo '&date=' . $_GET['date']; } ?>">Admission</a>
				<a class="small-btn<?php if($_GET['category'] == '3') { echo ' calCurrent'; } ?>" href="?category=3<?php if(isset($_GET['date'])) { echo '&date=' . $_GET['date']; } ?>">Athletics</a>
				<a class="small-btn<?php if($_GET['category'] == '2') { echo ' calCurrent'; } ?>" href="?category=2<?php if(isset($_GET['date'])) { echo '&date=' . $_GET['date']; } ?>">Law</a>
				<a class="small-btn<?php if($_GET['category'] == '6') { echo ' calCurrent'; } ?>" href="?category=6<?php if(isset($_GET['date'])) { echo '&date=' . $_GET['date']; } ?>">Student Activities</a>
				<a class="small-btn<?php if($_GET['category'] == 'all') { echo ' calCurrent'; } ?>" href="?category=all<?php if(isset($_GET['date'])) { echo '&date=' . $_GET['date']; } ?>">All</a>
			</div>
			<form class="quasi-heading text-center" method="GET">
				<select name="date">
					<?php
					// show previous 12 months, current, and future 12 months
					$months = 12; // number of months before and after current month
					$dt = new DateTime("first day of $dateToDisplay"); // first day of: requested month, or default to current month, as determined above
					$dt->modify("-$months month");
					for ($i = 0; $i <= $months * 2; $i++) {
						echo '<option value="' . $dt->format('Y-m') . '"';
						if($dt->format('Y-m') == $dateToDisplay) { echo ' selected="selected"'; }
						echo '>' . $dt->format('F Y') . '</option>' . "\n";
						$dt->modify('+1 month');
					}
					// also stay on same calendar, if one is selected
					if(!empty($_GET['category'])) {
						echo '<input type="hidden" name="category" value="' . $_GET['category'] . '" />';
					}
					?>
				</select>
				<input type="submit" value="Show">
			</form>
			<table class="stack unstriped">
				<caption class="show-for-sr"><?php echo date('F', mktime(0, 0, 0, $date[1], 10)) . ' ' . $date[0]; ?></caption>
				<thead>
					<tr>
						<th scope="col">Sun</th>
						<th scope="col">Mon</th>
						<th scope="col">Tue</th>
						<th scope="col">Wed</th>
						<th scope="col">Thu</th>
						<th scope="col">Fri</th>
						<th scope="col">Sat</th>
					</tr>
				</thead>
				<tbody>
					<?php
					// get raw data
					$events = stmu_get_mc_api_events($dateToDisplay, $category);
					// if API successfully returned events
					if($events != 'No events') {
						// determine first and last day to show
						$startDate = new DateTime("first day of $dateToDisplay midnight", new DateTimeZone('America/Chicago'));
						$startDay = $startDate->format('w');
						// If 1st day of current month is Sunday (0), do nothing.
						// If 1st day is Monday, go back 1 day to get Sunday from previous month.
						if($startDay == 1) {
							$startDate->modify('-1 day');
						} elseif($startDay == 2) {
							$startDate->modify('-2 days');
						} elseif($startDay == 3) {
							$startDate->modify('-3 days');
						} elseif($startDay == 4) {
							$startDate->modify('-4 days');
						} elseif($startDay == 5) {
							$startDate->modify('-5 days');
						} elseif($startDay == 6) {
							$startDate->modify('-6 days');
						}
						$endDate = new DateTime("last day of $dateToDisplay 11:59 pm", new DateTimeZone('America/Chicago'));
						$endDay = $endDate->format('w');
						// If last day of current month is Saturday (6), do nothing.
						// If last day is Saturday, add 1 day from next month.
						if($endDay == 5) {
							$endDate->modify('+1 day');
						} elseif($endDay == 4) {
							$endDate->modify('+2 days');
						} elseif($endDay == 3) {
							$endDate->modify('+3 days');
						} elseif($endDay == 2) {
							$endDate->modify('+4 days');
						} elseif($endDay == 1) {
							$endDate->modify('+5 days');
						} elseif($endDay == 0) {
							$endDate->modify('+6 days');
						}
						// display a div for each date in the range
						$dayCounter=0;
						$mobileStripe=0;
						for($i=$startDate; $i<=$endDate; $i->modify('+1 day')) {
							// if it's Sunday, include a wrapper div
							if($dayCounter==0 || $dayCounter%7==0) {
								$thisDay = '
						<tr>';
							} else {
								$thisDay = '';
							}
							$endTimeToday = $i->format('U') + 86340; // set end time as 23:59 PM on same day
							$thisDay .= '
							<td class="singleDate';
							if($i->format('m') != $date[1]) { $thisDay .= ' otherMonth'; }
							$thisDay .= '">';
							// only show this month's date and events
							if($i->format('m') == $date[1]) {
								$thisDay .= '<div class="dayNumber">';
								if($i->format('m') != $date[1]) { $thisDay .= '<span class="show-for-sr">' . $i->format('F') . ' </span>'; }
								$thisDay .= $i->format('d') . '</div>';
								$thisDay .= '
								<div class="dayName show-for-small-only">' . $i->format('l') . '</div>';
								// see if there are any events for this day
								$eventsToday=0;
								foreach($events as $event) {
									$eventTime = strtotime($event['start']);
									$eventISO = new DateTime($event['start']);
									if($event['allDay']) {
										$eventTimeDisplay = '';
									} else {
										$eventTimeDisplay = str_replace(array('am','pm'),array('a.m.','p.m.'),date('g:i a', strtotime($event['start'])));
									}
									if(($i->format('U') <= $eventTime) && ($eventTime <= $endTimeToday)) {
										$eventsToday++;
										$thisDay .= '<div itemscope itemtype="https://schema.org/Event" class="singleEvent';
										if($event['priority'] == 'high') { $thisDay .= ' featuredEvent'; }
										$thisDay .= '">'
											. '<meta itemprop="startDate" content="' . $eventISO->format("Y-m-d\TH:i:s-06:00") . '">'
											. '<span class="hide" itemprop="eventStatus" itemscope itemtype="https://schema.org/EventStatusType">EventScheduled</span>'
											. '<span class="hide" itemprop="location" itemscope itemtype="https://schema.org/Place"><span itemprop="name">St. Mary\'s University</span><span itemprop="address" itemscope itemtype="https://schema.org/PostalAddress"><span itemprop="addressLocality">San Antonio</span>, <span itemprop="addressRegion">TX</span></span></span>'
											. '<a itemprop="url" href="' . $event['url'] . '">'
											. '<strong>' . $eventTimeDisplay . '</strong> '
											. '<span itemprop="name">' . str_replace("\\", "", $event['title']) . '</span></a></div>';
									}
								}
							}
							// close .singleDate
							$thisDay .= '
							</td>';
							if($eventsToday > 0) {
								// increment mobile stripe counter
								$mobileStripe++;
								// add .withEvents class so the day shows on mobile; also alternate mobile stripe class
								if($mobileStripe % 2 == 0) {
									$thisDay = str_replace('<td class="singleDate', '<td class="withEvents mobileStripe singleDate', $thisDay);
								} else {
									$thisDay = str_replace('<td class="singleDate', '<td class="withEvents singleDate', $thisDay);
								}
							}
							// if it's Saturday, close the row
							if(($dayCounter+1)%7 == 0) {
								$thisDay .= '
						</tr>';
							}
							echo $thisDay;
							$dayCounter++;
						}
					} else {
						echo '<tr><td colspan=7>Sorry, the calendar is temporarily unavailable.</td></tr>';
					} ?>
				</tbody>
			</table>
			<div class="row">
				<div class="small-12 medium-4 columns text-center">
					<a class="ghost-btn" href="https://ems-app.stmarytx.edu/MasterCalendar/MasterCalendar.aspx">See full calendar</a>
				</div>
				<div class="small-12 medium-4 columns">
					<h2 class="h3" id="annual">Annual events</h2>
					<ul>
						<?php $id = $post->ID; wp_list_pages("child_of=$id&title_li=&depth=1"); ?>
					</ul>
				</div>
				<div class="small-12 medium-4 columns text-center">
					<a class="ghost-btn" href="https://ems-app.stmarytx.edu/mastercalendar/AddEvent.aspx">Submit an event</a>
				</div>
			</div>
		</div><!-- end block -->
	</main>
<?php get_footer(); ?>