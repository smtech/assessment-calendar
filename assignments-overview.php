<?php

require_once('common.inc.php');

use Battis\HierarchicalSimpleCache;
$cache = new HierarchicalSimpleCache($sql, basename(__FILE__, '.php'));
$cache->setLifetime(7*24*60*60);

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

$departments = array();
$allCourses = array();
$assessments = array();

try {
	$departments = $cache->getCache('departments');
	$allCourses = $cache->getCache('courses');
	$assessments = $cache->getcache(serialize($_REQUEST));
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
	
	if (empty($allCourses) || empty ($assessments)) {
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
			$allCourses[$department['id']] = $courses;
			foreach($courses as $course) {
				$cache->pushKey($course['id']);
				$assignments = $cache->getCache('assignments');
				if (empty($assignments) || $course['id'] == $sourceCourse) {
					$assignments = $api->get(
						"courses/{$course['id']}/assignments"
					);
					$cache->setCache('assignments', $assignments, rand(1, 24) * 60 * 60);
				}
				foreach($assignments as $assignment) {
					if(!empty($assignment['published'])) {
						if ($start <= $assignment['due_at'] && $assignment['due_at'] <= $end) {
							$assessments[$department['id']][$course['id']][] = $assignment;
						} else {
							$cache->pushKey('assignments');
							$details = $cache->getCache($assignment['id']);
							if (empty($details)) {
								$details = $api->get(
									"courses/{$course['id']}/assignments/{$assignment['id']}",
									array(
										'all_dates' => true
									)
								);
								$cache->setCache($assignment['id'], $details);
							}
							foreach ($details['all_dates'] as $date) {
								if ($start <= $date['due_at'] && $date['due_at'] <= $end) {
									$assessments[$department['id']][$course['id']][] = $assignment;
									break;
								}
							}
							$cache->popKey();
						}
					}
				}
				$cache->popKey();
			}
			$cache->popKey();
			$cache->setCache('courses', $allCourses);
			$cache->setCache(serialize($_REQUEST), $assessments, 60 * 60);
		}
	}
} catch (Exception $e) {
	$smarty->addMessage(get_class($e), $e->getMessage(), NotificationMessage::ERROR);
}

$smarty->assign('start', date('F j, Y', strtotime($start)));
$smarty->assign('end', date('F j, Y', strtotime($end)));
$smarty->assign('departments', $departments);
$smarty->assign('allCourses', $allCourses);
$smarty->assign('assessments', $assessments);
$smarty->display('assignments-overview.tpl');

?>