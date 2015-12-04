<?php

require_once('common.inc.php');

use Battis\HierarchicalSimpleCache;
$cache = new HierarchicalSimpleCache($sql, basename(__FILE__, '.php'));
$cache->setLifetime(24*60*60);

/* reset the cache for the course placement */
$sourceCourse = $_SESSION['toolProvider']->user->getResourceLink()->settings['custom_canvas_course_id'];

/*
	List all assessments during the assessment week (take dates as parameters)
	Sort by department and course (call out colors)
	Link back to course (and roster?) from page
*/

/* assume main account if none specified */
$accountId = null;
if (empty($_REQUEST['account'])) {
	$accountId = 1;
} elseif (is_numeric($_REQUEST['account'])) {
	$accountId = $_REQUEST['account'];
} else {
	$accountId = "sis_account_id:{$_REQUEST['account']}";
}

/* check dates -- default to "between today and this time next week" if not specified */
$start = date('Y-m-d');
if(!empty($_REQUEST['start'])) {
	$start = date('Y-m-d', strtotime($_REQUEST['start']));
}
$end = date('Y-m-d');
if (!empty($_REQUEST['end'])) {
	$end = date('Y-m-d', strtotime($_REQUEST['end']));
}
if ($end <= $start) {
	$end = date('Y-m-d', strtotime("$start +7 days"));
}

$masterCourses = array();
$assessments = array();
try {
	$departments = $cache->getCache('departments');
	if (empty($departments)) {
		$departments = array();
		foreach($api->get("accounts/{$accountId}/sub_accounts") as $department) {
			// FIXME only "real" departments have numeric SIS IDs, not a reliable differentiator!
			if (!empty($department['sis_account_id']) && is_numeric($department['sis_account_id'])) {
				$departments[$department['id']] = $department;
			}
		}			
		$cache->setCache('departments', $departments);
	}
	$smarty->assign('departments', $departments);
	foreach ($departments as $department) {
		$cache->pushKey($department['id']);
		$courses = $cache->getCache('courses');
		if (empty($courses)) {
			$courses = array();
			// TODO this particular set of parameters restricts us to only current courses (no historical data)
			foreach($api->get(
				"accounts/{$department['id']}/courses",
				array(
					'with_enrollments' => true,
					'published' => true,
					'completed' => false
				)
			) as $course) {
				$courses[$course['id']] = $course;
			}
			usort(
				$courses,
				function($a, $b) {
					if ($a['name'] == $b['name']) {
						return 0;
					}
					return ($a['name'] < $b['name'] ? -1 : 1);
				}
			);
			$cache->setCache('courses', $courses);
		}
		$masterCourses[$department['id']] = $courses;
		foreach($courses as $course) {
			$cache->pushKey($course['id']);
			$assignments = $cache->getCache('assignments');
			if (empty($assignments) || $course['id'] == $sourceCourse) {
				$assignments = $api->get(
					"courses/{$course['id']}/assignments",
					array(
						'bucket' => 'future' // FIXME this won't let us capture past assignments
					)
				);
				$cache->setCache('assignments', $assignments);
			}
			foreach($assignments as $assignment) {
				if(!empty($assignment['published']) && $start <= $assignment['due_at'] && $assignment['due_at'] <= $end) {
					$assessments[$department['id']][$course['id']][] = $assignment;
				}
			}
			$cache->popKey();
		}
		$cache->popKey();
	}
} catch (Exception $e) {
	$smarty->addMessage(get_class($e), $e->getMessage(), NotificationMessage::ERROR);
}

$smarty->assign('allCourses', $masterCourses);
$smarty->assign('assessments', $assessments);
$smarty->display('assessments.tpl');

?>