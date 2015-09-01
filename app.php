<?php

require_once('common.inc.php');

$cache = new Battis\HierarchicalSimpleCache($sql, basename(__FILE__, '.php'));
$courseId = $_SESSION['toolProvider']->user->getResourceLink()->settings['custom_canvas_course_id'];

/* get the list of students in this course */
$sections = $cache->getCache($courseId);
if ($sections === false) {
	$sections = $api->get(
		"courses/$courseId/sections",
		array(
			'include' => array('students')
		)
	);
	$cache->setCache($courseId, $sections);
}

/* query all the assessments affecting those students */
$students = array();
foreach ($sections as $section) {
	foreach ($section['students'] as $student) {
		$students[] = $student['id'];
	}
}
$response = $sql->query("
	SELECT *
		FROM `due_dates` as `d`
			LEFT JOIN `assessments` as `a` ON `d`.`assignment` = `a`.`id`
		WHERE
			(`d`.`student` = '" . implode("' OR `d`.`student` = '", $students) . "')
			AND (`a`.`course` != '{$courseId}'
		ORDER BY
			`d`.`due` ASC
");
$assessments = array();
$dueDates = array();
while ($dueDate = $response->fetch_assoc()) {
	if (empty($assessments[$dueDate['assignment']])) {
		$assessments[$dueDate['assignment']] = unserialize($dueDate['a.data']);
	}
	$dueDates[] = array('student' => $dueDate['student'])
}

/* build a month grid */
$month = (empty($_REQUEST['month']) ? date('m') : $_REQUEST['month']));
$year = (empty($_REQUEST['year']) ? date('Y') : $_REQUEST['year']);
$grid = array();
$week = 0;
for ($day = 1; $day <= date('t', strtotime("$year-$month-01"); $i++) {
	$day2 = (strlen($day) == 1 ? '0' : '') . $day;
	$dayOfWeek = date('w', strotime("$year-$month-$day2"));
	$grid[$week][$dayOfWeek] = date("Y-m-d", strtotime("$year-$month-$day2"));
	if ($dayOfWeek == 6) $week++;
}

$smarty->assign('grid', $grid);
$smarty->assign('assessments', $assessments);
$smarty->display('assessment-calendar.tpl');

?>