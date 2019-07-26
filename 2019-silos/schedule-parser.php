<?php
/************************** Banner Course Schedule ****************************/
// NOTE: If you intend to run this code, you must define a Banner webservice URL
// on line 175.
register_deactivation_hook(WP_PLUGIN_DIR.'/this-plugin/plugin.php', 'stmu_plugin_deactivate');
function stmu_2017_www_deactivate() {
	wp_clear_scheduled_hook('stmu_course_grabber'); // clears cron that parses course schedule JSON & saves it to WP DB
	wp_clear_scheduled_hook('stmu_course_program_updater'); // clears cron that parses WP DB course info & saves it to individual programs
}
register_activation_hook(WP_PLUGIN_DIR.'/this-plugin/plugin.php', 'stmu_plugin_activate');
function stmu_2017_www_activate() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	// Banner course table
	$table_name = $wpdb->prefix . 'banner_courses';
	$sql = "CREATE TABLE $table_name (
		id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
		prefix varchar(2) NOT NULL,
		code varchar(10) NOT NULL,
		name varchar(70) NOT NULL,
		days varchar(50),
		times varchar(30),
		level varchar(20) NOT NULL DEFAULT 'Undergraduate',
		semester mediumint(8) UNSIGNED NOT NULL,
		online tinyint UNSIGNED NOT NULL DEFAULT '0',
		evening tinyint UNSIGNED NOT NULL DEFAULT '0',
		PRIMARY KEY (id)
	) $charset_collate;";
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}
// First cron job - fires create_static_course_schedule() on lines 123-166
$timestamp = wp_next_scheduled('stmu_course_grabber');
if($timestamp == false) {
	wp_schedule_event(strtotime("+20 minutes"), 'hourly', 'stmu_course_grabber');
}
// Second cron job - fires parse_course_schedule() on lines 42-122
$timestamp2 = wp_next_scheduled('stmu_course_program_updater');
if($timestamp2 == false) {
	wp_schedule_event(strtotime("+40 minutes"), 'hourly', 'stmu_course_program_updater');
}
add_action('stmu_course_grabber', 'create_static_course_schedule');
add_action('stmu_course_program_updater', 'parse_course_schedule');
function parse_course_schedule() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'banner_courses';
	$allPrefixes = array();
	$postIdsToUpdate = array();
	// gather only the unique combinations of prefix+level ("prefix" is a two-character code like AC for accounting; "level" is undergraduate/graduate/doctoral)
	$dbObjects = $wpdb->get_results("SELECT DISTINCT(CONCAT(prefix, level)) as 'combo', prefix, level FROM $table_name GROUP BY CONCAT(prefix, level)");
	// deal with objects that contain multiple levels
	foreach($dbObjects as $key => $combo) {
		if(strpos($combo->level, ',')) {
			// isolate each level
			$levels = explode(', ', $combo->level);
			for($i=1; $i<count($levels); $i++) {
				// add separate levels as their own elements in the array
				$theCombo = $combo->prefix . $levels[$i];
				$dbObjects[] = (object) array('combo' => $theCombo, 'prefix' => $combo->prefix, 'level' => $levels[$i]);
			}
			// change the original object so it only contains the first level (since the others were already saved separately)
			$theCombo = $combo->prefix . $levels[0];
			$dbObjects[$key] = (object) array('combo' => $theCombo, 'prefix' => $combo->prefix, 'level' => $levels[0]);
		}
	}
	// make the array unique (some of the combos that were split may already have been in the array)
	$uniqueObjects = array_map('unserialize', array_unique(array_map('serialize', $dbObjects)));
	// find all courses with each level+prefix combination
	foreach($uniqueObjects as $object) {
		$courses = $wpdb->get_results("SELECT online, evening FROM $table_name WHERE prefix = \"$object->prefix\" AND level LIKE \"%$object->level%\"");
		$online = 0; $evening = 0;
		// loop through individual courses
		foreach($courses as $course) {
			if($course->online == 1) { $online = 1; }
			if($course->evening == 1) { $evening = 1; }
		}
		// mark that level+prefix combination as having "some" online, "some" evening if any had those flags, and save to an array instead of objects
		$allPrefixes[] = array($object->prefix, $object->level, $online, $evening);
	}
	// query all *published programs with a prefix* assigned
	$posts = $wpdb->get_results("SELECT post_id, meta_value FROM wp_postmeta LEFT JOIN wp_posts ON wp_postmeta.post_id = wp_posts.ID WHERE meta_key = 'course_prefix' AND post_status = 'publish'");
	for($i=0; $i<count($allPrefixes); $i++) {
		// find posts that match
		foreach($posts as $arraykey => $object) {
			// if the prefix matches
			if($allPrefixes[$i][0] == $object->meta_value) {
				// check the levels
				$levels = wp_get_post_terms($object->post_id, 'degree');
				$program_levels = array();
				foreach($levels as $level) {
					if($level->term_id == 678) { $program_levels[] = 'Doctoral';
					} elseif($level->term_id == 53) { $program_levels[] = 'Undergraduate';
					} elseif($level->term_id == 68) { $program_levels[] = 'Graduate'; } // Graduate = Masters
				}
				// if the WPProgram level (can be multiple) is contained in the WPBannerCourse level (also can be multiple):
				$online=0;
				$evening=0;
				$match=0;
				foreach($program_levels as $key => $wp_level) {
					if(strpos($allPrefixes[$i][1], $wp_level) !== false) {
						$match = 1;
						if($allPrefixes[$i][2] == 1) { $online = 1; }
						if($allPrefixes[$i][3] == 1) { $evening = 1; }
					}
				}
				// update the post (program) only one time, even if there were multiple levels assigned.
				if($match == 1) {
					$postIdsToUpdate[] = array(0 => $object->post_id, 1 => $online, 2 => $evening);
				}
			}
		}
	}
	// add or update postmeta to the corresponding programs
	for($i=0; $i<count($postIdsToUpdate); $i++) {
		$post_id = $postIdsToUpdate[$i][0];
		// online value
		$meta_value1 = $postIdsToUpdate[$i][1];
		update_post_meta($post_id, 'available_online', "$meta_value1");
		// evening value
		$meta_value2 = $postIdsToUpdate[$i][2];
		update_post_meta($post_id, 'available_evening', "$meta_value2");
	}
}
function create_static_course_schedule() {
	/********** Parse 3 semesters **********/
	date_default_timezone_set('America/Chicago');
	$currentMonth = date('m');
	$currentYear = date('Y');
	// clear old data
	global $wpdb;
	$table_name = $wpdb->prefix . 'banner_courses';
	$truncate = $wpdb->query("TRUNCATE TABLE $table_name");
	// Jan-Feb-Mar-Apr-May
	if($currentMonth < 6) {
		// get spring semester
		$semester = $currentYear . '10';
		get_banner_courses($semester);
		// get summer semester
		$semester = $currentYear . '20';
		get_banner_courses($semester);
		// get fall semester
		$semester = $currentYear . '30';
		get_banner_courses($semester);
	// June-July
	} elseif($currentMonth < 8) {
		// get summer semester
		$semester = $currentYear . '20';
		get_banner_courses($semester);
		// get fall semester
		$semester = $currentYear . '30';
		get_banner_courses($semester);
		// get spring of next year semester
		$semester = ($currentYear+1) . '10';
		get_banner_courses($semester);
	// Aug-Sep-Oct-Nov-Dec
	} else {
		// get fall semester
		$semester = $currentYear . '30';
		get_banner_courses($semester);
		// get spring semester
		$semester = ($currentYear+1) . '10';
		get_banner_courses($semester);
		// get summer semester
		$semester = ($currentYear+1) . '20';
		get_banner_courses($semester);
	}
}
/********** End parse 3 semesters **********/
/********** Get & save courses from Banner **********/
// Each semester is formed as "YYYY"+(10, 20, or 30). Example: 201710 for spring, 201720 for summer, 201730 for fall.
function get_banner_courses($semester) {
	// 1. Retrieve Banner courses
	$ch = curl_init();
	$webservice = "";
	curl_setopt($ch, CURLOPT_URL, "$webservice" . $semester . '/');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$headers[] = 'Accept: application/xml';
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	$rawCourses = curl_exec($ch);
	curl_close($ch);
	$xml = simplexml_load_string($rawCourses);
	$courses = $xml->course;
	
	// 2. Save to DB
	global $wpdb;
	for($i=0; $i<count($courses); $i++) {
		// Pull variables from the objects
		$prefix = $courses[$i]->subjectCode;
		$code = $courses[$i]->number;
		$name = $courses[$i]->title;
		$days = '';
		$days = $courses[$i]->meetings->days;
		// convert times in 24-hour format (i.e. "1900") to 12-hour format with : and am/pm
		$startTime = $courses[$i]->meetings->startTime;
		$endTime = $courses[$i]->meetings->endTime;
		if(!empty($startTime) && !empty($endTime)) {
			$startTime = substr($startTime, 0, 2) . ':' . substr($startTime, 2, 2);
			$startTime = date('g:i a', strtotime("$startTime"));
			$endTime = substr($endTime, 0, 2) . ':' . substr($endTime, 2, 2);
			$endTime = date('g:i a', strtotime("$endTime"));
			$times = "$startTime to $endTime";
		} else {
			$times = '';
		}
		$level = $courses[$i]->levels;
		$semester = $courses[$i]->termCode;
		if(stripos($courses[$i]->attributes, 'Online') || stripos($courses[$i]->instructionalMethodDesc, 'Online') || $courses[$i]->sequence == 'OL' || $courses[$i]->instructionalMethodCode == 'OL') { // stripos = case insensitive
			$online = 1;
		} else {
			$online = 0;
		}
		if($courses[$i]->meetings->startTime >= 1700) {
			$evening = 1;
		} else {
			$evening = 0;
		}
		
		// save to DB
		$wpdb->insert('wp_banner_courses',
			array(
				'prefix' => "$prefix",
				'code' => "$code",
				'name' => "$name",
				'days' => "$days",
				'times' => "$times",
				'level' => "$level",
				'semester' => "$semester",
				'online' => "$online",
				'evening' => "$evening"
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%d'
			)
		);
	}
}
/********** End get & save courses from Banner **********/
/********** Add shortcode to display courses in a program - that way if one program doesn't want to show courses they can easily exclude it, and content can appear in middle of page **********/
add_shortcode('course_schedule', 'show_stmu_courses');
function show_stmu_courses($atts, $content = null) {
	$atts = shortcode_atts(array(
		'prefix' => 'AC',
		'level' => 'Undergraduate',
	), $atts);
	$prefix = $atts['prefix'];
	global $wpdb;
	$courseContent = '';
	$courseRows = $wpdb->get_results("SELECT * FROM wp_banner_courses WHERE prefix = '$prefix' AND level like '%$level%'");
	if($courseRows) {
		$courseContent = '<table><caption class="show-for-sr">Current course schedule</caption>';
		$courseContent .= '<thead><tr><th scope="col">Semester</th><th scope="col">Code</th><th scope="col">Course</th><th scope="col">Day and time</th><th>Online?</th></thead><tbody>';
		$online = 0;
		foreach($courseRows as $row) {
			$days = '';
			// convert full day names to just first (or first and second) letter
			$fullDayNames = $row->days;
			if(!empty($fullDayNames)) {
				$daysSplit = explode(', ', $fullDayNames);
				for($i=0; $i<count($daysSplit); $i++) {
					if(substr($daysSplit[$i], 0, 2) == 'Th') {
						$days .= 'Th ';
					} else {
						$days .= substr($daysSplit[$i], 0, 1) . ' ';
					}
				}
			}
			$times = str_replace('pm', 'p.m.', $row->times);
			$times = str_replace('am', 'a.m.', $times);
			$semester = $row->semester;
			$year = substr($semester, 0, 4);
			$semesterCode = substr($semester, 4, 2);
			if($semesterCode == '10' ) { $semesterName = 'Spring '; } elseif($semesterCode == '20') { $semesterName = 'Summer '; } else { $semesterName = 'Fall '; }
			$courseContent .= '<tr><td>' . $semesterName . $year . '</td>';
			$courseContent .= '<td>' . $row->prefix . ' ' . $row->code . '</td>';
			$courseContent .= '<td><a href="https://appssbprd.stmarytx.edu/BPRD/bwckctlg.p_disp_course_detail?cat_term_in=' . $semester . '&subj_code_in=' . $row->prefix . '&crse_numb_in=' . $row->code . '">' . $row->name . '</a></td>';
			$courseContent .= '<td>' . $days . '<br/>' . $times . '</td><td>';
			if($row->online == 1) { $courseContent .= 'Yes'; $online = 1; }
			$courseContent .= '</td></tr>';
		}
		$courseContent.= '</tbody></table>';
		if($online == 1) { $courseContent = '<em>Some courses in this program are available online.</em>' . $courseContent; }
	}
	return $courseContent;
}
?>