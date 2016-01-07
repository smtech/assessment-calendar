{extends file="page.tpl"}

{block name="content"}

<div class="container">
	<p>{$start} - {$end}</p>
</div>

<div class="container">
	<ul class="nav nav-pills" role="tablist">
		{assign var="activeTab" value=false}
		{foreach $departments as $department}
			<li role="presentation"{if !$activeTab} class="active"{$activeTab = $department['id']}{/if}><a href="#{$department['id']}" aria-controls="{$department['id']}" role="tab" data-toggle="tab">{$department['name']}</a></li>
		{/foreach}
	</ul>
	
	<div class="tab-content">
		{foreach $allCourses as $deptId => $deptCourses}
			<div role="tabpanel" class="tab-pane{if $activeTab == $deptId} active{/if}" id="{$deptId}">
				<div class="container">
					{foreach $deptCourses as $course}
						<div class="panel panel-{if isset($assessments[$deptId][$course['id']])}primary{else}default{/if}">
							<div class="panel-heading">
								<p class="panel-title"><a href="{$course['html_url']}">{$course['name']}</a> <small class="pull-right"><a target="_top" href="{$smarty.session.canvasInstanceUrl}/courses/{$course['id']}/users">Roster</a></small></p>
							</div>
							{if isset($assessments[$deptId][$course['id']])}
								<div class="panel-body">
									{foreach $assessments[$deptId][$course['id']] as $assessment}						
										<p>
											<a target="_top" href="{$assessment['html_url']}">{$assessment['name']}</a>
											<small>due {date('g:i a l, M. j', strtotime($assessment['due_at']))}</small>
										</p>
									{/foreach}
								</div>
							{/if}
						</div>
					{/foreach}
				</div>
			</div>
		{/foreach}
	</div>
</div>
{/block}