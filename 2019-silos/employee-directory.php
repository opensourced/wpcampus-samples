<?php
/**************************** Employee Directory ******************************/
// NOTE: If you intend to run this code, it needs to run on a WordPress website
// that has posts in a "faculty" and/or "employee" custom post type. You must
// also define a Banner webservice URL on line 14 because ours will not allow
// connections from outside our domain.
$timestamp = wp_next_scheduled('stmu_directory_action');
if($timestamp == false) {
	wp_schedule_event(time(), 'hourly', 'stmu_directory_action');
}
add_action('stmu_directory_action', 'create_static_employee_directory');
function create_static_employee_directory() {
	// 1A. Retrieve Banner employees
	$webservice = ""; // Must set your webservice URL here for the directory to work.
	$jsonDirectory = file_get_contents($webservice);
	$allEmployees = json_decode($jsonDirectory, true);
	$bannerEmployees = $allEmployees['directoryItems'];

	// 1B. Insert Rattler Man, who isn't in Banner
	$bannerEmployees[] = array(
		'lastName' => 'Man',
		'firstName' => 'Rattler',
		'middleInitial' => '',
		'preferredName' => '',
		'legalName' => '',
		'suffix' => '',
		'orgnTitle' => array('0' => ''),
		'jobDesc' => 'Mascot',
		'bldg' => '',
		'room' => 'Classified',
		'city' => 'San Antonio',
		'state' => 'TX',
		'zip' => '78228',
		'areaCode' => '210',
		'phone' => '4363327',
		'ext' => '3327',
		'email' => 'ucomm@stmarytx.edu',
		'campusBox' => 'Campus Box 75',
	);
	// Alphabetize the array, because Rattler Man just got added to the end
	usort($bannerEmployees, function($p1, $p2) {
		return strnatcasecmp("$p1[lastName] $p1[firstName]", "$p2[lastName] $p2[firstName]");
	});
	
	// 2. Retrieve WordPress employees
	// 2A. Law Faculty (all are in the "faculty" CPT)
	$lawFaculty = wp_remote_get('https://law.stmarytx.edu/wp-json/wp/v2/faculty/?per_page=100'); // 100 is max allowed in one request
	$numPages = wp_remote_retrieve_header($lawFaculty, 'x-wp-totalpages');
	$lawFacultyBody = wp_remote_retrieve_body($lawFaculty);
	$lawFacultyDecoded = json_decode($lawFacultyBody, true);
	if($numPages > 1) { // Get the rest of the faculty. Start at page 2, continue to == numPages.
		for($i=2; $i<($numPages+1); $i++) {
			$nextUrl = 'https://law.stmarytx.edu/wp-json/wp/v2/faculty/?per_page=100&page=' . $i;
			$nextPage = wp_remote_get("$nextUrl");
			$bodyVar = 'lawFaculty' . $i; // create variable variable name
			$$bodyVar = wp_remote_retrieve_body($nextPage);
			$decodeVar = 'lawFacultyDecoded' . $i;					
			$$decodeVar = json_decode(${$bodyVar}, true);
			// merge this page into the existing decoded law faculty array
			$lawFacultyDecoded = array_merge($lawFacultyDecoded, $$decodeVar);
		}
	}
	$lawFaculty = array();
	for($i=0; $i<count($lawFacultyDecoded); $i++) {
		// Get featured image
		$imgApi = wp_remote_get('https://law.stmarytx.edu/wp-json/wp/v2/media/' . $lawFacultyDecoded[$i][featured_media]);
		$imgResponse = wp_remote_retrieve_body($imgApi);
		$imgDecoded = json_decode($imgResponse, true);
		// Add employee
		$wpFaculty[] = array(
			'id' => $lawFacultyDecoded[$i][id],
			'email' => $lawFacultyDecoded[$i][acf][faculty_staff_email],
			'imgUrl' => $imgDecoded['media_details']['sizes']['thumbnail']['source_url'],
			'link' => $lawFacultyDecoded[$i][link]
		);
	}
	// 2B. Www Employees (some are in a "faculty" CPT while others are in "employee" CPT)
	$args = array(
		'post_type' => array('faculty','employee'),
		'posts_per_page' => -1,
		'fields' => 'ids'
	);
	$wpIds = get_posts($args);
	// add postmeta
	for($i=0; $i<count($wpIds); $i++) {
		$wpEmail = get_post_meta($wpIds[$i], 'faculty_staff_email', true);
		$wpImageInfo = wp_get_attachment_image_src((get_post_thumbnail_id($wpIds[$i])), 'thumbnail');
		$wpImage = $wpImageInfo[0];
		if(get_post_type($wpIds[$i]) == 'faculty') { $wpLink = get_permalink($wpIds[$i]);
		} else {
			$wpLink = get_post_meta($wpIds[$i], 'bio_link', true);
		}
		$wpFaculty[] = array(
			'id' => $wpIds[$i],
			'email' => $wpEmail,
			'imgUrl' => $wpImage,
			'link' => $wpLink
		);
	}
	// 3. Combine Banner & WP data
	global $wpdb;
	$employeePix = array();
	for($i=0; $i<count($bannerEmployees); $i++) {
		$bannerEmail = $bannerEmployees[$i]['email'];
		if(!empty($bannerEmail)) {
			// loop through WP employees to see if any email matches
			for($x=0; $x<count($wpFaculty); $x++) {
				if($wpFaculty[$x]['email'] == $bannerEmail) {
					$bannerEmployees[$i]['link'] = $wpFaculty[$x]['link'];
					$bannerEmployees[$i]['site'] = $wpFaculty[$x]['site'];
					if(!empty($wpFaculty[$x]['imgUrl'])) {
						$bannerEmployees[$i]['imgUrl'] = $wpFaculty[$x]['imgUrl'];
						// Replace staging and CDN URLs with Prod URLs
						$wwwImg = str_replace('http://s20923.p221.sites.pressdns.com', 'https://www.stmarytx.edu', $wpFaculty[$x]['imgUrl']);
						$employeePix[] = array($wpFaculty[$x]['email'], $wwwImg);
					} else {
						$defaultImg = get_site_icon_url();
						$defaultImg = str_replace('http://s20923.p221.sites.pressdns.com', 'https://www.stmarytx.edu', $defaultImg);
						$employeePix[] = array($wpFaculty[$x]['email'], $defaultImg);
					}
				}
			}
		}
	}
	// Write combined (Banner + wwwWP + lawWP) data to a file
	$file = fopen(__DIR__.'/all-employees.js', 'w');
	fwrite($file, 'var jsonEmployees =');
	fwrite($file, json_encode($bannerEmployees));
	fwrite($file, ';');
	fclose($file);
}
/**************************** Employee Directory ******************************/
?>