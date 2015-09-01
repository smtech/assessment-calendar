<?php
	
namespace smtech\AssessmentCalendar;

class AssignmentScanner /*extends \Battis\AutoCrontabJob*/ {
	
	/** @var \smtech\CanvasAPIviaLTI\Environment */
	private $env;
	
	public function setEnvironment ($env) {
		if ($env instanceof \smtech\CanvasAPIviaLTI\Environment) {
			$this->env = $env;
		} else {
			throw new AssignmentScanner_Exception(
				'Expected an Environment object, received a ', get_class($env),
				AssignmentScanner_Exception::ENVIRONMENT
			);
		}
	}
	
	public function scheduledJob() {
		
		/* get all currently active courses */
		$courses = $this->env->api->get(
			'accounts/132/courses',
			array(
				'published' => 'true',
				'completed' => 'false',
				'hide_enrollmentless_courses' => 'true'
			)
		);
		
		/* walk through the courses... */
		foreach ($courses as $course) {
			
			/* get all sections for each course, with student enrollments */
			$_sections = $this->env->api->get(
				"courses/{$course['id']}/sections",
				array(
					'include' => array('students')
				)
			);
			
			/* build a quickly-searchable cache of sections and students for this course */
			$sections = array();
			$students = array();
			foreach ($_sections as $section) {
				$sections[$section['name']] = $section;
				foreach ($section['students'] as $student) {
					$students[$student['name']] = $section['name'];
				}
			}
			
			/* walk through all assignments in the course */
			$assignments = $this->env->api->get(
				"courses/{$course['id']}",
				array(
					'include' => array('all_dates')
				)
			);
			foreach ($assignments as $assignment) {
				
				/* if the assignment is an assessment... */
				if (preg_match('/test/i', $assignment['name'])) {
					
					/* ...add this assignment to the list of assessments */
					$this->env->sql->query("INSERT INTO `courses` (`id`, `data`) VALUES ('{$course['id']}', '" . $this->env->sql->real_escape_string(serialize($course)) . "')");
					$this->env->sql->query("INSERT INTO `assessments` (`id`, `course`, `data`) VALUES ('{$assignment['id']}', '{$course['id']}', '" . $this->env->sql->real_escape_string(serialize($assignment)) . "')");

					/* walk through all of the due dates for this assignment... */
					$base = false;
					$assignees = array();
					foreach ($assignment['all_dates'] as $date) {
						
						/* save the "base" date for later processing */
						if ($date['base']) {
							$base = $date;
							
						/* ...but process all other due dates */
						} else {
							
							/* is it a due date for a section? */
							if (array_key_exists($date['title'], $sections)) {
								
								/* add all the students in the section to the due date list */
								foreach($sections[$date['title']]['students'] as $assignee) {
									if (!in_array($assignee['id'], $assignees)) {
										$this->env->sql->query("INSERT INTO `students` (`id`, `data`) VALUES ('{$assignee['id']}', '" . $this->env->sql->real_escape_string(serialize($assignee)) . "')");
										$this->env->sql->query("INSERT INTO `due_dates` (`assessment`, `student`, `due`) VALUES ('{$assignment['id']}', '{$assignee['id']}', '{$date['due_at']}')");
										$assignees[] = $assignee['id']; /* note that we've added all these students */
									}
								}
								
							/* is it a due date for an individual student? */
							} elseif (array_key_exists($date['title'], $students)) {
								
								/* find that student in his/her section and add them to the due date list */
								foreach ($sections[$students[$date['title']]]['students'] as $assignee) {
									if ($assignee['name'] == $date['title']) {
										$this->env->sql->query("INSERT INTO `students` (`id`, `data`) VALUES ('{$assignee['id']}', '" . $this->env->sql->real_escape_string(serialize($assignee)) . "')");
										$this->env->sql->query("INSERT INTO `due_dates` (`assessment`, `student`, `due`) VALUES ('{$assignment['id']}', '{$assignee['id']}', '{$date['due_at']}')");
										$assignees[] = $assignee['id'];
										break;
									}
								}
							}
						}
					}
					
					/* if there was a base due date, add everyone who wasn't already added to the due date list */
					if ($base) {
						foreach ($sections as $section) {
							foreach ($section['students'] as $assignee) {
								if (!in_array($assignee['id'], $assignees)) {
									$this->env->sql->query("INSERT INTO `students` (`id`, `data`) VALUES ('{$assignee['id']}', '" . $this->env->sql->real_escape_string(serialize($assignee)) . "')");
									$this->env->sql->query("INSERT INTO `due_dates` (`assessment`, `student`, `due`) VALUES ('{$assignment['id']}', '{$assignee['id']}', '{$base['due_at']}')");
									$assignees[] = $assignee['id'];
								}
							}
						}
					}
				}
			} 
		}
	}
}

class AssignmentScanner_Exception extends \CanvasAPIviaLTI_Exception {
	
	/** Environment-related error */
	const ENVIRONMENT = 1;
}