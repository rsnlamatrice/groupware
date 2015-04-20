<?php
/* ED150211
 * copy of viewweek.php
 */
require_javascript('og/CalendarToolbar.js');
require_javascript('og/CalendarFunctions.js');
require_javascript('og/EventPopUp.js');
require_javascript('og/CalendarPrint.js');
require_javascript('og/EventRelatedPopUp.js'); 
$genid = gen_id();

//$max_events_to_show = user_config_option('displayed events amount');
//if (!$max_events_to_show) $max_events_to_show = 15;
$max_events_to_show = 999;
?>

<script>
	scroll_to = -1;
	og.ev_cell_dates = [];
	og.events_selected = 0;
	og.eventSelected(0);
        og.config.genid = '<?php echo $genid ?>';
</script>

<?php
	
	$today = DateTimeValueLib::now();
	$defaultDate = DateTimeValueLib::now()->add('M', -2)->setDay(1);//ED150212
	$year = isset($_GET['year']) ? $_GET['year'] : (isset($_SESSION['year']) ? $_SESSION['year'] : $defaultDate->getYear());
	$month = isset($_GET['month']) ? $_GET['month'] : (isset($_SESSION['month']) ? $_SESSION['month'] : $defaultDate->getMonth());
	$day = isset($_GET['day']) ? $_GET['day'] : (isset($_SESSION['day']) ? $_SESSION['day'] : $defaultDate->getDay());
	
	$_SESSION['year'] = $year;
	$_SESSION['month'] = $month;
	$_SESSION['day'] = $day;
	
	$user_filter = $userPreferences['user_filter'];
	$status_filter = $userPreferences['status_filter'];
        $task_filter = $userPreferences['task_filter'];
	
	$user = Contacts::findById(array('id' => $user_filter));
	if ($user == null) $user = logged_user();
	
	$use_24_hours = user_config_option('time_format_use_24');
	$date_format = user_config_option('date_format');
	if($use_24_hours) $timeformat = 'G:i';
	else $timeformat = 'g:i A';
	
	define('FORMAT_DATE_KEY', 'Y-m-d');
	
	echo stylesheet_tag('event/week.css');

	if (user_config_option("start_monday")) {
		$startday = $day - date("N", mktime(0, 0, 0, $month, $day, $year)) + 1; // beginning of the week, monday
	} else {
		$startday = $day - date("N", mktime(0, 0, 0, $month, $day, $year)); // beginning of the week, sunday
	}

	$endday = $startday + 7; // end of week
	
	$today->add('h', logged_user()->getTimezone());
	$currentday = $today->format("j");
	$currentmonth = $today->format("n");
	$currentyear = $today->format("Y");
	$currentMonday = $today->getMondayOfWeek()->format(FORMAT_DATE_KEY);
	$drawHourLine = false;
	
	$lastday = date("t", mktime(0, 0, 0, $month, 1, $year)); // # of days in the month
	
	$date_request = new DateTimeValue(mktime(0, 0, 0, $month, $startday, $year));
	$date_start = new DateTimeValue(mktime(0, 0, 0, $month, $startday, $year));
	$date_end = clone $date_start;
	$date_start = $date_start->add('M', 0)->setDay(1)->getMondayOfWeek(); //ED150211
	//$date_end = new DateTimeValue(mktime(0, 0, 0, $month, $startday, $year));
	$date_end = $date_end->add('M', +7)->setDay(1)->getMondayOfWeek(); //ED150211
//	$date_start->add('h', logged_user()->getTimezone());
//	$date_end->add('h', logged_user()->getTimezone());
	$month = $date_start->getMonth();
	$startday = $date_start->getDay();
	$year = $date_start->getYear();
	$tasks = array();
	if($task_filter != "hide"){
	    $tasks = ProjectTasks::getRangeTasksByUser($date_start, $date_end, ($user_filter != -1 ? $user : null), $task_filter);
	}

	$tmp_tasks = array();
	foreach ($tasks as $task) {
		$tmp_tasks = array_merge($tmp_tasks, replicateRepetitiveTaskForCalendar($task, $date_start, $date_end));
	}
	
	
	$events = ProjectEvents::getRangeProjectEvents($date_start, $date_end, ($user_filter != -1 ? $user : null), $status_filter); 
	$events = array_merge($tmp_tasks, $events);

	$dates = array(); //datetimevalue for each day of week. [ _$memberId][]
	$results = array();
	$allday_events_count = array();
	$alldayevents = array();
	$today_style = array();
	
	$task_starts = array();
	$task_ends = array();

	$events_assoc = array();
	// references object ids to get members
	foreach ($events as $event) {
		$events_assoc[strval($event->getId())] = $event;
	}
	$events = $events_assoc;
	
	$members_by_objects = array();
	//var_dump(array_keys($events_assoc), $members_by_objects, active_context_members(false));
	$members = ObjectMembers::getMembersByObjects(array_keys($events_assoc), $members_by_objects, active_context_members(false));
	//$children_members = ObjectMembers::getChildrenMembers( active_context_members(false));
	//var_dump($children_members);
	//die();
	//references dates and members events
	foreach ($events as $eventId => $event) {
		$member = $members_by_objects[$eventId];
		if(!$member){
			//var_dump($event->getId(), $members_by_objects[$event->getId()]);
			continue;
		}
		
		//date du lundi
		if(is_a($event, 'ProjectEvent')){
			//$date = $event->getStart()->getMondayOfWeek();
			$eventDate = new DateTimeValue($event->getStart()->getTimestamp() + 3600 * logged_user()->getTimezone());
		}
		elseif(is_a($event, 'ProjectTask')){ 
			//$date = $event->getStartDate()->getMondayOfWeek();
			$eventDate = new DateTimeValue($event->getStartDate()->getTimestamp() + 3600 * logged_user()->getTimezone());
			
		}
		else {
			echo 'event de type inconnu : '; var_dump($event);
			continue;//TODO ?!
		}
		$date = $eventDate->getMondayOfWeek();
		$strDate = $date->format(FORMAT_DATE_KEY);
		if(!isset($dates[$strDate]))
			$dates[$strDate] = array();
		$memberId = $member->getId();
		if(!isset($dates[$strDate]['_'.$memberId]))
			$dates[$strDate]['_'.$memberId] = array();
		//else
		//	var_dump($strDate, count($dates[$strDate]['_'.$memberId]));
		$dates[$strDate]['_'.$memberId][] = $event;
		
		if ($event instanceof ProjectEvent
		&& $event->getRepeatNum()
		&& $event->getRepeatD()) {
			$fromDate = new DateTimeValue($eventDate->getTimestamp());
			$toDate = new DateTimeValue($eventDate->getTimestamp() + 24 * 3600 * ($event->getRepeatNum()-1));
			$repeatedEvents = $event->getRepetitiveInstances($fromDate, $toDate);
			foreach($repeatedEvents as $event){
				$date = new DateTimeValue($event->getStart()->getTimestamp() + 3600 * logged_user()->getTimezone());
				$date = $date->getMondayOfWeek();
				$strDate = $date->format(FORMAT_DATE_KEY);
				if(!isset($dates[$strDate]))
					$dates[$strDate] = array();
				if(!isset($dates[$strDate]['_'.$memberId]))
					$dates[$strDate]['_'.$memberId] = array();
				//else
				//	var_dump($strDate, count($dates[$strDate]['_'.$memberId]));
				$dates[$strDate]['_'.$memberId][] = $event;
			}
		}
		
	}
//var_dump($date_start->format('d/m/Y'), $date_end->format('d/m/Y'), '$members', count($members), '$events', count($events),'$tmp_tasks', count($tmp_tasks), active_context_members(false));
	
	/*ED150212*/
	$members_column_width = 170 + 16;
	$date_width = 48;
	$min_depth = 0;
	$max_depth = 0;
			
	define('PX_HEIGHT', count($members) < 10 ? 68 : 42);
	define('PIX_CELL_OVER', 2);
	
	$allgridHeight = PX_HEIGHT;
	$members_top = array();
	
	$users_array = array();
	$companies_array = array();
	foreach($users as $u)
		$users_array[] = $u->getArrayInfo();
	foreach($companies as $company)
		$companies_array[] = $company->getArrayInfo();
	
?>
<div id="calHiddenFields">
	<input type="hidden" id="hfCalUsers" value="<?php echo clean(str_replace('"',"'", str_replace("'", "\'", json_encode($users_array)))) ?>"/>
	<input type="hidden" id="hfCalCompanies" value="<?php echo clean(str_replace('"',"'", str_replace("'", "\'", json_encode($companies_array)))) ?>"/>
	<input type="hidden" id="hfCalUserPreferences" value="<?php echo clean(str_replace('"',"'", str_replace("'", "\'", json_encode($userPreferences)))) ?>"/>
        <input id="<?php echo $genid?>type_related" type="hidden" name="type_related" value="only" />
</div>


<div class="calendar" style="padding:0px;height:100%;overflow:hidden;" id="cal_main_div" onmouseup="og.clearPaintedCells();">
<div id="calendarPanelTopToolbar" class="x-panel-tbar" style="width:100%;height:28px;display:block;background-color:#F0F0F0;"></div>
<div id="calendarPanelSecondTopToolbar" class="x-panel-tbar" style="width:100%;height:28px;display:block;background-color:#F0F0F0;"></div>
<div id="<?php echo $genid."view_calendar"?>" class="css-ruled">  
<table style="width:100%;height:100%;">
<tr>
<td>
	<table style="width:100%;height:100%;">
	<tr>
	<td class="coViewHeader" colspan=2 rowspan=1>
	<div class="coViewTitle">
		<table style="width:100%;"><tr><td style="height:25px;vertical-align: middle;">
			<span id="chead0">
			<?php 
				$weeknumber = date("W", mktime(0, 0, 0, $month, $startday + 1, $year));
				echo date($date_format, mktime(0, 0, 0, $month, $startday, $year))
					. " (" . lang("week number x", $weeknumber) . ") -&gt; "; 
				$weeknumber = date("W", mktime(0, 0, 0, $date_end->getMonth(), $date_end->getDay() + 1, $date_end->getYear()));
				echo date($date_format, mktime(0, 0, 0, $date_end->getMonth(), $date_end->getDay() + 1, $date_end->getYear()))
					. " (" . lang("week number x", $weeknumber) . ") - "; 
				echo ($user_filter == -1 ? lang('all users') : lang('calendar of', clean($user->getObjectName())));?></span>
		</td><td style="height:25px; vertical-align:middle; padding-right:10px;"><?php 
		/*if (config_option("show_feed_links")) {
			renderCalendarFeedLink();
		}*/
		?></td></tr></table>
	</div>		
	</td>
	</tr>
	<tr>
		<td class="coViewBody user-view-trash-checked" style="padding:0px;height:100%;" colspan=2>
		<div id="chrome_main2" style="width:100%; height:100%">
		<div id="allDayGridContainer" class="inset grid">
		<div id="allDayGrid">
		<?php					
			$current_week_left = 0;
			$date_end_timespamp = $date_end->getTimestamp();
			$working_date = new DateTimeValue( $date_start->getTimestamp() );
			$dates_left = array();
			$createEventDrag_args = array();
			//weeks loop
			do {	
				$week = $working_date->format('w');
				$day_of_week = $working_date->getDayOfWeek();
				$day_of_month = $working_date->getDay();
				$strDate = $working_date->format(FORMAT_DATE_KEY);
		
				$dtv_temp = $working_date;
				$p = get_url('event', 'viewweek', array(
					'day' => $dtv_temp->getDay(),
					'month' => $dtv_temp->getMonth(),
					'year' => $dtv_temp->getYear(),
					'view_type' => 'viewdate'
				));
				$t = get_url('event', 'add', array(
					'day' => $dtv_temp->getDay(),
					'month' => $dtv_temp->getMonth(),
					'year' => $dtv_temp->getYear()
				));
		?>
				<div id="alldaycelltitle_<?php echo $strDate ?>" class="chead cell-<?=$strDate?> top-mbr0 <?=$currentMonday == $strDate ? 'vy-today' : 'cheadNotToday'?> x-tree-lines">
					<span id="chead<?php echo $strDate ?>">
						<a class="internalLink" href="<?php echo $p; ?>"><?php echo $dtv_temp->format('d/m') . ' sem.' . $dtv_temp->format('W'); ?></a>
					</span>
				</div>
				<div class="chead-expand x-tree-arrows cell-<?=$strDate?>">
					<img class="x-tree-elbow-plus" src="s.gif"/>
				</div>
				<div id="allDay<?php echo $strDate ?>" class="allDayCell cell-<?php echo $strDate ?>"></div>

				<div id="alldayeventowner_<?php echo $strDate ?>" class="cell-<?php echo $strDate ?>" data-date="<?php $dtv_temp->format(FORMAT_DATE_KEY)?>"></div>
		<?php
				$dates_left[$strDate] = $current_week_left;
				
				$current_week_left += $date_width;
				
				//ED150211
				//1 week after
				$working_date->add('d', 7);
			}
			while($working_date->getTimestamp() <= $date_end_timespamp);
		?>
		
		</div><!-- allDayGridContainer -->
		
		<div class="nav-btn nav-toolbar toolbar-left x-tree-arrows">
			<div class="user-view">
				<button class="db-ico ico-trash checked">&nbsp;</button>
			</div>
			<div class="nav-btn btn-left">
				<img class="x-tree-elbow-end-minus" src="s.gif"/>
			</div>
			<!--div class="x-tab-scroller-left x-unselectable x-tab-scroller-left-disabled " id="ext-gen301" style="height: 30px;"></div-->
		</div>
		<div class="nav-btn btn-right x-tree-arrows">
			<img class="x-tree-elbow-end-plus" src="s.gif"/>
			<!--div class="x-tab-scroller-right x-unselectable " id="ext-gen307" style="height: 30px;"></div-->
		</div>
		
	</div>
<?php if(true){?>
	<div id="gridcontainer" class="toprint">	
			<div id="calowner">  
				<table cellspacing="0" cellpadding="0" border="0">
					<tr>
						<td id="rowheadcell">
							<div id="rowheaders">										
							<?php
								$nMember = 0;
								$grid_height = 0;
									
								// Members headers column 
								foreach ($members as $memberId => $member){
									if(!is_object($member)){
										var_dump('$member ! object', $member);
										continue;
									}
									if($min_depth === 0)//first
										$min_depth = $member->getDepth();
									if($max_depth < $member->getDepth())
										$max_depth = $member->getDepth();
									$members_top['_'.$memberId] = $grid_height;
/* first column : members tree */			?>
<div class="rhead mbr-depth<?= ($member->getDepth() - $min_depth) ?>" id="rhead<?php echo $memberId?>" data-member="<?=$memberId?>" >
<div class="rheadtext x-tree-lines">
	<div><?php 
		if($member->getHasChild()){
			?><img src="s.gif" class="x-tree-ec-icon x-tree-elbow-minus"/><?php
		 }
		?><img src="s.gif" class="x-tree-node-icon ico-color<?=$member->getColor()?>"/>
	</div>
	<div><?php
		 echo clean($member->getName());
	?></div>
	<div class="member-tools">
		<button class="ico-trash">&nbsp;</button>
		<div class="ico-expand">&nbsp;</div>
	</div>
</div>
</div>
<?php												
									$grid_height += PX_HEIGHT;
									$nMember++;
								}
							?>

							</div>
						</td>
						<td id="gridcontainercell">
							<?php /* event cells */ ?>
							<div id="gridcontainernav">
							<div id="grid" class="grid">										
								
							<?php
								$prev_depth = 0;
								// Member row line
								foreach ($members as $memberId  => $member){
									if($prev_depth > $member->getDepth()){
										$attr_class = "mbr-child";
									} elseif($prev_depth == $member->getDepth()){
										$attr_class = "mbr-sibling";
									} else {
										$attr_class = "mbr-ancestor";
									}
									$top = $members_top['_'.$memberId];
							?>
<div id="r<?php echo $memberId?>" class="hrule <?php echo $attr_class?> top-mbr<?php echo $memberId?>"></div>
<?php
									$prev_depth = $member->getDepth();
								}
?>
<?php /*<div id="r-bottom" class="hrule"></div>*/ ?>

<div id="eventowner" class="eventowner" onclick="og.disableEventPropagation(event) ">
<?php
								$nWeek = 0;
								$date_end_timespamp = $date_end->getTimestamp();
								
								$working_date = new DateTimeValue( $date_start->getTimestamp() );
								
								$added_dates = array();
								$added_divs = array();
								
								//weeks loop
								do {	
									$week = $working_date->format('w');
									$date = $working_date;
									$strDate = $date->format(FORMAT_DATE_KEY);
									$current_week_left = $dates_left[$strDate];
									$left = $current_week_left;
									$top = 0;
									$added_dates[] = $strDate;
									
									foreach ($members as $memberId => $member){
										$top = $members_top['_'.$memberId];
								
										$div_id = 'h' . $strDate . "_" . $memberId;
										
										$added_divs[] = $div_id;
?>
<div class="vy-cell cell-<?=$strDate?> top-mbr<?=$memberId?><?= $strDate == $currentMonday ? ' vy-today' : ''?>" id="<?php echo $div_id?>"></div>
<?php
?>

<?php									}

									 ?><div id="vd<?php echo $strDate ?>" class="cell-<?=$strDate?> vy-vd"></div>
<?php									if($dates[$strDate]){
										$cells = array();
										
										$occup = array(); //keys: memberid - pos
										foreach ($dates[$strDate] as $parentKey => $memberTasks){
											$memberId = substr($parentKey, 1);
											foreach($memberTasks as $event_id => $event){
												
												if(isset($cells[$parentKey]))
													$cells[$parentKey]++;
												else
													$cells[$parentKey] = 0;
													
												if(is_a($event, 'ProjectEvent')){
													//$eventDate = $event->getStart()->getMondayOfWeek();
													$eventDate = new DateTimeValue($event->getStart()->getTimestamp() + 3600 * logged_user()->getTimezone());
													
												}
												elseif(is_a($event, 'ProjectTask')){ 
													//$eventDate = $event->getStartDate()->getMondayOfWeek();
													$eventDate = new DateTimeValue($event->getStartDate()->getTimestamp() + 3600 * logged_user()->getTimezone());
												}
												getEventLimits($event, $eventDate, $event_start, $event_duration, $end_modified);
												
												$subject = clean($event->getObjectName());
												
												$ws_color = $event->getObjectColor($event instanceof ProjectEvent ? 1 : 12);
												
												cal_get_ws_color($ws_color, $ws_style, $ws_class, $txt_color, $border_color);	
												
												$top = $members_top[$parentKey];
												$top +=  + $cells[$parentKey] * PIX_CELL_OVER;
												$bottom = $top + PX_HEIGHT - 1;
												$height = $bottom - $top;
												
												$evs_same_time = 0;
												$i = $parentKey;
												
												$posHoriz = 0;
												$canPaint = true;
	
												$width = $date_width;
												$left_offset = 0.25 + $cells[$parentKey] * PIX_CELL_OVER;
												$left = $current_week_left + $left_offset;
												$width -= 0.5;
												
												$event_duration->add('s', 1);
												
												if ($event instanceof ProjectEvent) {
													$real_start = new DateTimeValue($event->getStart()->getTimestamp() + 3600 * logged_user()->getTimezone());
													$real_duration = new DateTimeValue($event->getDuration()->getTimestamp() + 3600 * logged_user()->getTimezone());
												} else if ($event instanceof ProjectTask) {
													if ($event->getStartDate() instanceof DateTimeValue) {
														$real_start = new DateTimeValue($event->getStartDate()->getTimestamp() + 3600 * logged_user()->getTimezone());
													} else {
														$real_start = $event_start;
													}
													if ($event->getDueDate() instanceof DateTimeValue) {
														$real_duration = new DateTimeValue($event->getDueDate()->getTimestamp() + 3600 * logged_user()->getTimezone());
													} else {
														$real_duration = $event_duration;
													}
												}
												
												$pre_tf = $real_start->getDay() == $real_duration->getDay() ? '' : 'D j, ';
												$ev_hour_text = (!$event->isRepetitive() && $real_start->getDay() != $event_start->getDay()) ? "... ".format_date($real_duration, $timeformat, 0) : format_date($real_start, $timeformat, 0);
												
												$assigned = "";
												if ($event instanceof ProjectTask && $event->getAssignedToContactId() > 0) {
													$assigned = "<br>" . lang('assigned to') .': '. $event->getAssignedToName();
													$task_desc = purify_html($event->getText());
													if(!$subject)
														$subject = $task_desc;
													$tipBody = lang('assigned to') .': '. clean($event->getAssignedToName()) . (trim(clean($event->getText())) != '' ? '<br><br>' . trim($task_desc) : '');
												} else {
													$tipBody = format_date($real_start, 'D d/m/Y')
														. $assigned
														. (trim(clean($event->getDescription())) != '' ? '<br><br>' . clean($event->getDescription()) : '');
													if(!$subject)
														$subject = clean($event->getDescription());
													$tipBody = str_replace("\r", '', $tipBody);
													$tipBody = str_replace("\n", '<br>', $tipBody);
												}
												if (strlen_utf($tipBody) > 200) $tipBody = substr_utf($tipBody, 0, strpos($tipBody, ' ', 200)) . ' ...';
												
												$ev_duration = DateTimeValueLib::get_time_difference($event_start->getTimestamp(), $event_duration->getTimestamp());
												$id_suffix = "_$strDate_$parentKey";
												
												/* TODO add description in .chip
												?><script>
													addTip('w_ev_div_' + '<?php echo $event->getId() . $id_suffix ?>', <?php echo json_encode(clean($event->getObjectName())) ?>
													       , <?php echo json_encode($tipBody);?>);
												</script>
												<?php */?>
	<?php
												$bold = "bold";
												if ($event instanceof Contact || $event->getIsRead(logged_user()->getId())){
													$bold = "normal";
												}

						?><div id="w_ev_div_<?php echo $event->getId() . $id_suffix?>" class="chip cell-<?= $strDate ?> top-mbr<?= substr($parentKey,1) ?>" <?php
							?> style="margin-top: <?php echo $left_offset?>px; margin-left: <?php echo $left_offset?>px;" <?php
							?> data-date="<?=$real_start->format(FORMAT_DATE_KEY)?>" data-member="<?=$memberId?>">
						<div class="t1 <?php echo $ws_class ?>" style="<?php echo $ws_style ?>;border-color:<?php echo $border_color ?>"></div>
						<div class="t2 <?php echo $ws_class ?>" style="<?php echo $ws_style ?>;border-color:<?php echo $border_color ?>"></div>
						<div id="inner_w_ev_div_<?php echo $event->getId() . $id_suffix?>" class="chipbody edit og-wsname-color-<?php echo $ws_color?>">
						<div style="border-color:<?php echo $border_color ?>;"><?php
							?><table style="width:100%;"><tr><td><?php
								if (FALSE && $event instanceof ProjectEvent) {
									/* case à cocher pour sélection multiple */
									?><input type="checkbox" style="width:13px;height:13px;vertical-align:top;margin:2px 0 0 2px;border-color: <?php echo $border_color ?>;" id="sel_<?php echo $event->getId()?>" name="obj_selector" onclick="og.eventSelected(this.checked);"></input><?php
								} 
								?><a href='<?php echo get_url($event instanceof ProjectEvent ? 'event' : 'task', 'view', array(
										'view' => 'viewweek',
										'id' => $event->getId(),
										'user_id' => $user_filter
									)); ?>' <?php
								?> class='internalLink' <?php
								?> style="color:<?php echo $txt_color?>!important;"> <?php
									//ED150410 : event defined 'in week'
									if(is_a($event, 'ProjectEvent')
									   && $event->isInWeekEvent()){
										$label = 'sem ' . $week;
									}
									else
										$label = $real_start->format('j M');
									?><span name="w_ev_div_<?php echo $event->getId() . $id_suffix?>_info" style="font-weight:<?php echo $bold ?>;"><?= $label ?></span><?php
									
									if(is_a($event, 'ProjectEvent')
										&& $event->isPermissionGroupDefined()){
										?><span class="permission_group db-ico ico-permissions" style="border-color:<?php echo $txt_color?>"<?php
										?> title="<?= htmlspecialchars($event->getPermissionGroupName()) ?>"></span><?php
									}
									
									$label = $subject; 
									?><div name="w_ev_div_<?php echo $event->getId() . $id_suffix?>_info2" class="cell-info2"><?= $label ?></div><?php
								?></a>
							<tr style="height:100%;">
								<td style="width:100%;" colspan="2"><div style="height: <?php echo $height - PX_HEIGHT ?>px;"></div></td>
							</tr>
							</table>
						</div>
						</div>
						<div class="b2 <?php echo  $ws_class?>" style="<?php echo  $ws_style?>;border-color:<?php echo $border_color ?>"></div>
						<div class="b1 <?php echo  $ws_class?>" style="<?php echo  $ws_style?>;border-color:<?php echo $border_color ?>"></div>
						</div><?php 
						if (!logged_user()->isGuest() && $event instanceof ProjectEvent) {
							/*Resize*/
							//TODO vertical
							if (false && !$end_modified) {  
								?><script>og.setResizableEvent('w_ev_div_<?php echo $event->getId() . $id_suffix?>', '<?php echo $event->getId()?>');</script><?php
							}
							//Drag
							$is_repetitive = $event->isRepetitive();
							$createEventDrag_args[] = array('w_ev_div_'. $event->getId() . $id_suffix, $event->getId(), $is_repetitive, $event_start->format('Y-m-d H:i:s'), 'event', false, 'ev_dropzone', $memberId);
							/*if (!options.isGuest) og.createEventDrag('w_ev_div_<?php echo $event->getId() . $id_suffix?>', '<?php echo $event->getId()?>', <?php echo $is_repetitive ?>, '<?php echo $event_start->format('Y-m-d H:i:s') ?>', 'event', false, 'ev_dropzone'); */
						}
											}//foreach member
										}//foreach event
										
									} //if $dates[$strDate]

									$nWeek++;
								
									//ED150211
									//1 week later
									$working_date->add('d', 7);
								}//date 
								while($working_date->getTimestamp() <= $date_end_timespamp);
								
								?>
</div><!-- eventowner -->
										</div><!-- grid -->
										</div><!-- gridcontainernav -->
									</td><td id="ie_scrollbar_adjust" style="width:0px;"></td>
								</tr>
							</table>
						</div><!-- calowner -->
				</div><!-- gridcontainer -->
			</div>
			<?php }?>
			</td>
			</tr>
		</table>
	</td>
</tr>
</table>
</div>
</div>


<script>
	(function(){
		function getFavoriteStyleSheet() {
			if (document.styleSheets) // If browser can play with stylesheets
				return document.styleSheets[document.styleSheets.length - 1];
			return false;
		}
		function getCSSPageRuleName(ruleName) {
			var genid = '<?php echo $genid."view_calendar.css-ruled"?>';
			if (ruleName.indexOf(',') > 0)
				return '#' + genid + ' ' + ruleName.split(',').join(', #' + genid + ' ');
			return '#' + genid + ' ' + ruleName;
		}
		//delete all rules containing .css-ruled as selector
		function clearCSSPageRules() {
			for (var i=1; i<document.styleSheets.length; i++) { // For each stylesheet
				styleSheet=document.styleSheets[i];          // Get the current Stylesheet
				var ii=0;                                        // Initialize subCounter.
				var cssRule=false;                               // Initialize cssRule. 
				do {                                             // For each rule in stylesheet
					if (styleSheet.cssRules) {                    // Browser uses cssRules?
						cssRule = styleSheet.cssRules[ii];         // Yes --Mozilla Style
					} else {                                      // Browser usses rules?
						cssRule = styleSheet.rules[ii];            // Yes IE style. 
					}                                             // End IE check.
					if (cssRule && cssRule.selectorText       // If we found a rule...
					&& cssRule.selectorText.indexOf('.css-ruled')>0) { //  match ruleName?
						if (styleSheet.cssRules) {           // Yes, deleting...
						   styleSheet.deleteRule(ii);        // Delete rule, Moz Style
						} else {                             // Still deleting.
						   styleSheet.removeRule(ii);        // Delete rule IE style.
						}                                    // End IE check.
					}                                             // end found cssRule
					ii++;                                         // Increment sub-counter
				} while (cssRule) 
			}  
		}
		/******************************************************************************************
		 * 		http://www.hunlock.com/blogs/Totally_Pwn_CSS_with_Javascript 
		 ******************************************************************************************/
		function getCSSRule(ruleName, deleteFlag, styleSheet) {               // Return requested style obejct
		   if (document.styleSheets) {                            // If browser can play with stylesheets
			if (styleSheet) {
				var cssRule=getCSSRuleInStyleSheet(ruleName, deleteFlag, styleSheet);
				return cssRule;
			}
		      for (var i=0; i<document.styleSheets.length; i++) { // For each stylesheet
			 styleSheet=document.styleSheets[i];          // Get the current Stylesheet
			 var cssRule=getCSSRuleInStyleSheet(ruleName, deleteFlag, styleSheet)
			 if(cssRule) return cssRule;                      // end While loop
		      }                                                   // end For loop
		   }                                                      // end styleSheet ability check
		   return false;                                          // we found NOTHING!
		}                                                         // end getCSSRule
		
		function getCSSRuleInStyleSheet(ruleName, deleteFlag, styleSheet) {               // Return requested style obejct
			ruleName=getCSSPageRuleName(ruleName.toLowerCase());                       // Convert test string to lower case.
			var ii=0;                                        // Initialize subCounter.
			 var cssRule=false;                               // Initialize cssRule. 
			 do {                                             // For each rule in stylesheet
			    if (styleSheet.cssRules) {                    // Browser uses cssRules?
			       cssRule = styleSheet.cssRules[ii];         // Yes --Mozilla Style
			    } else {                                      // Browser usses rules?
			       cssRule = styleSheet.rules[ii];            // Yes IE style. 
			    }                                             // End IE check.
			    if (cssRule && cssRule.selectorText)  {       // If we found a rule...
			       if (cssRule.selectorText.toLowerCase()==ruleName) { //  match ruleName?
				  if (deleteFlag=='delete') {             // Yes.  Are we deleteing?
				     if (styleSheet.cssRules) {           // Yes, deleting...
					styleSheet.deleteRule(ii);        // Delete rule, Moz Style
				     } else {                             // Still deleting.
					styleSheet.removeRule(ii);        // Delete rule IE style.
				     }                                    // End IE check.
				     return true;                         // return true, class deleted.
				  } else {                                // found and not deleting.
				     return cssRule;                      // return the style object.
				  }                                       // End delete Check
			       }                                          // End found rule name
			    }                                             // end found cssRule
			    ii++;                                         // Increment sub-counter
			 } while (cssRule)                                // end styleSheet ability check
		   return false;                                          // we found NOTHING!
		}                                                         // end getCSSRule 
		
		function killCSSRule(ruleName, styleSheet) {                          // Delete a CSS rule   
		   return getCSSRule(ruleName, 'delete', styleSheet);                  // just call getCSSRule w/delete flag.
		}                                                         // end killCSSRule
		
		function addCSSRule(ruleName, styles, styleSheet) {                           // Create a new css rule
		   if (document.styleSheets) {                         // Can browser do styleSheets?
			var cssRule = getCSSRule(ruleName);
			if (cssRule)	                        	// if rule does exist...
				return cssRule;
			if (!styleSheet) {
				var i = 1;//document.styleSheets.length-1;		// 0 can not be used
				 styleSheet = document.styleSheets[i]; // styleSheet to use.
			}
			if (styleSheet) {
				/*if (styleSheet.addRule) {           // Browser is IE?
				    styleSheet.addRule(ruleName, null,0);      // Yes, add IE style
				 } else { */                                        // Browser is IE?
					if(styles === undefined)
						styles = '';
					//console.log(getCSSPageRuleName(ruleName)+' { ' + styles + ' }');
					styleSheet.insertRule(getCSSPageRuleName(ruleName)+' { ' + styles + ' }', styleSheet.cssRules.length); // Yes, add Moz style.
				 //}                                                // End browser check
			}
			
		   }                                                      // End browser ability check.
		   return getCSSRule(ruleName, styleSheet);                           // return rule we just created.
		} 
		/******************************************************************************************/

	<?php	/* ED150219 */
		?>var options = {
			dayShortNames : ['dim', 'lun', 'mar', 'mer', 'jeu', 'ven', 'sam']
			, dates : <?= json_encode($added_dates) ?>
			, members : <?= json_encode(array_keys($members_top)) ?>
			, max_depth : <?= $max_depth - $min_depth ?>
			, row_height: <?php echo PX_HEIGHT?>
			, day_width: <?= $date_width ?>
			, isGuest : <?= logged_user()->isGuest() ? 'true' : 'false'?>
		}
		<?php if (!logged_user()->isGuest()) {
		?>, methods = {
			cell_onmouseover : function(){
				if (!og.selectingCells) {
					og.overCell(this.id);
				}
				else og.paintSelectedCells(this.id);
			},
			cell_onmouseout : function(){
				if (!og.selectingCells){
					og.resetCell(this.id);
				}
			},
			cell_onmousedown : function(){
				var strDate = this.getAttribute('data-date')
				, memberId = this.className.replace(/^.*\btop-mbr(\d+).*$/, '$1');
				og.selectStartDateTime(strDate.substr(8,2), strDate.substr(5,2), strDate.substr(0,4), 0, 0);
				og.resetCell(this.id);
				og.paintingDay = strDate;
				og.paintSelectedCells(this.id);
				og.currentMemberId = memberId; //ED150223
			},
			cell_onmouseup : function(){
				var strDate = this.getAttribute('data-date');
				//ED150304 adds hour
				og.showEventPopup(strDate.substr(8,2), strDate.substr(5,2), strDate.substr(0,4), (new Date()).getHours(), 0, <?php echo ($use_24_hours ? 'true' : 'false'); ?>
				     , strDate.substr(8,2) + '/' + strDate.substr(5,2) + '/' + strDate.substr(0,4), '<?php echo $genid?>'
				     , '<?php echo ProjectEvents::instance()->getObjectTypeId()?>');
			},
			
			vy_cell_init : function(dom, strDate){
				if (dom === undefined)
					dom = this;
				else if (dom.jquery)
					dom = dom[0];
				
				if (strDate)
					dom.setAttribute('data-date', strDate);
					
				if (!options.isGuest) {
					dom.onmouseover = methods.cell_onmouseover;
					dom.onmouseout = methods.cell_onmouseout;
					dom.onmousedown = methods.cell_onmousedown;
					dom.onmouseup = methods.cell_onmouseup;
					//var ev_dropzone = new Ext.dd.DropZone(dom.id, {ddGroup:'ev_dropzone'});
				}
			},
			alldayeventowner_onclick : function(){
				var strDate = this.getAttribute('data-date');
				og.showEventPopup(strDate.substr(8,2), strDate.substr(5,2), strDate.substr(0,4), -1, -1, <?php echo ($use_24_hours ? 'true' : 'false'); ?>
					, strDate.substr(8,2) + '/' + strDate.substr(5,2) + '/' + strDate.substr(0,4)
					, '<?php echo $genid?>', '<?php echo ProjectEvents::instance()->getObjectTypeId()?>');
			},
			disableEventPropagation : function(){
				og.disableEventPropagation(event);
			},
			clearPaintedCells : function(){
				og.clearPaintedCells();
			},
			get_date : function($cell){
				var className = $cell.jquery ? $cell.attr('class') : $cell.className;
				var regex = /^.*(\bcell-(\d{4}-\d{2}-\d{2})).*$/;
				if(regex.test(className))
					return className.replace(regex, '$2');
				return false;
			},
			get_member_id : function($cell){
				var className = $cell.jquery ? $cell.attr('class') : $cell.className;
				var regex = /^.*(\btop-mbr(\d+)).*$/;
				if(regex.test(className))
					return className.replace(regex, '$2');
				return false;
			},
			/* expands a week as 7 day columns */
			chead_expand_onclick : function(event){
				//toggle direction
				var $img = $(this).children('img')
				, expanding = $img[0].className.indexOf('x-tree-elbow-plus') >= 0;
				$img[0].className = $img[0].className.replace(expanding ? 'x-tree-elbow-plus' : 'x-tree-elbow-minus', expanding ? 'x-tree-elbow-minus' : 'x-tree-elbow-plus');
				
				var strMonday = methods.get_date($(this))
				, mondayDate = new Date(strMonday)
				, mondayLabel = mondayDate.format('d/m');
				var $headers = $('#allDayGrid')
				, $grid = $('#grid')
				, $grid_container_nav = $('#gridcontainernav');
				var $column_width = $headers.find('.chead:first').width();
				
				if(expanding){
					$headers.find('.cell-' + strMonday).each(function(){
						if(this.className.indexOf('allDayCell')>=0
						|| this.className.indexOf('chead-expand')>=0
						|| this.id.indexOf('alldayeventowner_') == 0)
							return;
						var $this = $(this);
						$this.addClass('cell-day');
						var $insertAfter = $this;
						for(var day = 1; day < 7; day++){
							var dayDate = new Date(strMonday);
							dayDate.setDate(mondayDate.getDate()+day);
							var dayLabel = dayDate.format('d/m') + '<br>' + options.dayShortNames[dayDate.getDay()]
							, strDate = dayDate.format('Y-m-d');
							og.ev_cell_dates[strDate] = {day: strDate.substr(8,2), month: strDate.substr(5,2), year: strDate.substr(0,4)};
							var $clone = $this.clone();
							$clone.addClass('cell-day');
							if(day == 6)
								$clone.addClass('day-sunday');
							$clone[0].className = $clone[0].className.replace(strMonday, strDate);
							$clone[0].id = $clone[0].id.replace(strMonday, strDate);
							//$clone.css('left', $this.position().left + $column_width * day);
							if($clone.hasClass('chead'))
								$clone.find('.internalLink').html(dayLabel);
							$clone.insertAfter($insertAfter);
							$insertAfter = $clone;
						}
					});
					
					$grid.find('.cell-' + strMonday + ':not(.chip)').each(function(){
						var $this = $(this)
						, $insertAfter = $this;
						$this.addClass('cell-day');
						for(var day = 1; day < 7; day++){
							var dayDate = new Date(strMonday);
							dayDate.setDate(mondayDate.getDate()+day);
							var strDate = dayDate.format('Y-m-d');
							
							var $clone = $this.clone();
							$clone.addClass('cell-day');
							if(day == 6)
								$clone.addClass('day-sunday');
							$clone[0].className = $clone[0].className.replace(strMonday, strDate);
							$clone[0].id = $clone[0].id.replace(strMonday, strDate);
							//$clone.css('left', $this.position().left + $column_width * day);
							
							if($clone.hasClass('vy-cell')){
								$clone.css('background-color', 'none');/* due to hover */
								methods.vy_cell_init($clone, strDate);
							}
							$clone.insertAfter($insertAfter);
							$insertAfter = $clone;
						}
					});
					//events
					var $events = $grid.find('.chip.cell-' + strMonday + '[data-date]');
					
					$grid.find('.chip.cell-' + strMonday).each(function(){
						this.className = this.className
								.replace(/\s*\bh-expanded\b/g, '')
								+ ' h-expanded';
					});
					for(var day = 1; day < 7; day++){
						var dayDate = new Date(strMonday);
						dayDate.setDate(mondayDate.getDate()+day);
						var strDate = dayDate.format('Y-m-d');
						// Set monday class to date class
						$events.filter('[data-date="' + strDate + '"]').each(function(){
							this.className = this.className
								.replace(strMonday, strDate)
								.replace(/\s*\bh-expanded\b/g, '')
								+ ' h-expanded';
						});
					}
					
					//remove style due to mouseover
					$('.vy-cell[style]:visible').removeAttr('style');
					
				}
				else { //collapse
					$headers.find('.cell-' + strMonday + '.cell-day').removeClass('cell-day');
					$grid.find('.cell-' + strMonday + '.cell-day').removeClass('cell-day');
					
					
					$grid.find('.chip.cell-' + strMonday).each(function(){
						this.className = this.className
							.replace(/\s*\bh-expanded\b/g, '');
					});
					for(var day = 1; day < 7; day++){
						var dayDate = new Date(strMonday);
						dayDate.setDate(mondayDate.getDate()+day);
						var strDate = dayDate.format('Y-m-d');
						$headers.find('.cell-' + strDate).remove();
						// Set date class to monday
						$grid.find('.chip.cell-' + strDate).each(function(){
							this.className = this.className
								.replace(strDate, strMonday)
								.replace(/\s*\bh-expanded\b/g, '');
						});
						$grid.find('.cell-' + strDate).remove();
					}
				}
				methods.set_dates_left($('#alldaycelltitle_' + strMonday), expanding);
				//throw "debug";
			}, /*chead_expand_onclick*/
			
			
			set_dates_left : function($left_cell, expanding){
				var left_strDate = methods.get_date($left_cell)
				, left = $left_cell.position().left
				, width = options.day_width
				, colWidth
				, styleSheet = false
				, prevWidth = expanding ? width * 2 : width;
				$('#allDayGrid .chead').each(function(){
					var strDate = methods.get_date($(this));
					if(strDate <= left_strDate)
						return;
					
					left += prevWidth;
					var css_rule = getCSSRule('.cell-' + strDate, styleSheet);
					if(css_rule){
						styleSheet = css_rule.parentStyleSheet;
						killCSSRule('.cell-' + strDate, styleSheet);//TODO css_rule
					}
					css_rule = addCSSRule('.cell-' + strDate, 'left : ' + left + 'px;', styleSheet);
					
					if (/\bcell-day\b/.test(this.className)
					&& !/\bday-sunday\b/.test(this.className))
						prevWidth = 2* width;
					else
						prevWidth = width;
				});
			},
			
			/* shows right or left hidden columns */
			navigate : function(){
				
				var $headers = $('#allDayGrid')
				, $grid = $('#grid')
				, $grid_container_nav = $('#gridcontainernav');
				function hide_date(strDate){
					if(strDate.jquery)
						strDate = methods.get_date(strDate);
					if(!strDate){
						throw('date inconnue');
						return;
					}
					$headers.find('.cell-' + strDate).hide();
				}
				function show_date(strDate){
					if(strDate.jquery)
						strDate = methods.get_date(strDate);
					if(!strDate){
						throw('date inconnue');
						return;
					}
					$headers.find('.cell-' + strDate).show();
					
				}
				var direction = /btn-right/.test(this.className) ? 1 : -1;
				
				var $cell = $headers.find('.chead:visible:first');
				if(direction < 0){
					var $prev_cell = $cell.prevAll('.chead:first');
					if($prev_cell.length === 0)
						return;
				}
				var offset = $cell.width();
				
				//hrule
				left = - $grid.position().left;
				left += direction * offset;
				$grid.find('.hrule').css('left', left + 'px');
				
				//grid
				var left = $grid.position().left;
				left -= direction * offset;
				$grid.css('left', left);
				
				//headers
				left = $headers.position().left;
				left -= direction * offset;
				$headers.css('left', left);
				//$headers.css('width', $headers.width() + direction * offset);
				
				if(direction > 0)
					hide_date($cell);
				else 
					show_date($prev_cell);
			},
						
			
			
			
			is_visible : function(dom, user_hide_all){
				return !/parent-tree-collapsed/.test(dom.className)
					&& (!user_hide_all || !/user-hide/.test(dom.className));//!/display\s?:\s*none/.test(rhead.getAttribute('style'));
			},
			is_user_hide_all : function(dom){
				if (dom.jquery)
					return dom.parents('.coViewBody:first').hasClass('user-view-trash-checked');
				return $(dom).parents('.coViewBody:first').hasClass('user-view-trash-checked');
				
			},
			/* Defines members top style */
			set_members_top : function($header){
				var height = options.row_height
				, top = $header[0].offsetTop
				, parentMemberId = $header[0].id.substr('rhead'.length)
				, skip_depth = false
				, styleSheet = getFavoriteStyleSheet()
				, rowHeight = height
				, css_rule
				, before = true
				, lookingForFirstVisible = false
				, rhead
				, isVisible
				, user_hide_all = methods.is_user_hide_all($header);
				for(var nMember = 0; nMember < options.members.length; nMember++){
					memberId = options.members[nMember].substr(1);
					if (before) {
						if (parentMemberId == memberId) {
							before = false;
							rhead = document.getElementById('rhead' + memberId);
							isVisible = methods.is_visible(rhead, user_hide_all);
							if (!isVisible) {//due to toggle_user_view_trash
								lookingForFirstVisible = true;
								continue;
							}
							if (rhead.getAttribute('r-height')) 
								rowHeight = parseFloat(rhead.getAttribute('r-height'));
							
							/* adjusts cells height */
							css_rule = getCSSRule('.vy-cell.top-mbr' + memberId, styleSheet);
							if(css_rule){
								styleSheet = css_rule.parentStyleSheet;
								killCSSRule('.vy-cell.top-mbr' + memberId, styleSheet);
							}
							css_rule = addCSSRule('.vy-cell.top-mbr' + memberId, 'height: ' + rowHeight + 'px');
							
							//next row top
							top += rowHeight;
						}
						continue;
					}
					else
						rhead = document.getElementById('rhead' + memberId);
					isVisible = methods.is_visible(rhead);
					if (!isVisible)
						continue;
					if (lookingForFirstVisible){
						top = rhead.offsetTop;
						lookingForFirstVisible=false;
					}
					css_rule = getCSSRule('.top-mbr' + memberId, styleSheet);
					if(css_rule){
						styleSheet = css_rule.parentStyleSheet;
						killCSSRule('.top-mbr' + memberId, styleSheet);
					}
					
					css_rule = addCSSRule('.top-mbr' + memberId, 'top : ' + top + 'px;', styleSheet);
										
					if (rhead.getAttribute('r-height')) 
						top += parseFloat(rhead.getAttribute('r-height'));
					else
						top += height;
				}
				//throw('ICIC ICI CI ');
			},
			
			/* tree collapse/expand */
			toggle_tree_node : function(){
				//toggle direction
				var $img = $(this)
				, $rhead = $img.parents('.rhead:first')
				, root_depth = parseInt($rhead[0].className.replace(/^.*mbr-depth(\d+).*$/, '$1'))
				, expanding = $img[0].className.indexOf('x-tree-elbow-plus') >= 0
				, max_depth = options.max_depth
				, $grid = $('#grid')
				, parentMemberId = $rhead[0].id.substr('rhead'.length)
				, user_hide_all = methods.is_user_hide_all($grid)
				;
				
				if (expanding) {
					
					methods.set_row_height($rhead, false);
					
					var /*skip_depth = false
					,*/ collapsed_depth = false
					, collapsed_parent_id = false
					, collapsed_parent_ids = {};
					$rhead.nextAll('div.rhead').each(function(){
						var depth = parseInt(this.className.replace(/^.*mbr-depth(\d+).*$/, '$1'));
						if(depth <= root_depth)
							return false; //stop looping
						/*if(skip_depth)
							if(skip_depth < depth)
								return; //next
							else if(skip_depth == depth)
								skip_depth = false;
						if(user_hide_all && /\buser-hide\b/.test(this.className)){
							skip_depth = depth;
							return;
						}*/
						
						var memberId = this.id.substr('rhead'.length);
						if(collapsed_depth && collapsed_depth < depth){
							collapsed_parent_ids[memberId] = collapsed_parent_id;
							return;//next
						}
						else if(collapsed_depth == depth){
							collapsed_parent_id = false;
							collapsed_depth = false;
						}
						this.className = this.className.replace(/\bparent-tree-collapsed/, '');
						
						if(this.className.indexOf('tree-collapsed') >= 0){
							collapsed_parent_id = memberId;
							collapsed_depth = depth;
						}
						if(this.className.indexOf('row-expanded') >= 0)
							methods.set_row_height($(this), false);
						//$(this).show();
					});
					//reset children class
					$grid.find('.top-mbr' + parentMemberId + '[data-member]').each(function(){
						var $this = $(this);
						var memberId = $this.attr('data-member');
						if(memberId == parentMemberId)
							return;
						//set class to set top position
						if(collapsed_parent_ids[memberId])
							memberId = collapsed_parent_ids[memberId];
						this.className = this.className.replace(/\btop-mbr\d+/, 'top-mbr' + memberId);
					});
					$img
						.removeClass('x-tree-elbow-plus')
						.addClass('x-tree-elbow-minus');
					$rhead
						.removeClass('tree-collapsed')
						.addClass('tree-expanded');
				}
				else {//collapse
					$rhead.nextAll('div.rhead').each(function(){
						var depth = parseInt(this.className.replace(/^.*mbr-depth(\d+).*$/, '$1'));
						if(depth <= root_depth)
							return false;//stop
						var $this = $(this);
						$this.addClass('parent-tree-collapsed');
						//if ($this.is(':visible'))
						//	$this.hide();
						var memberId = this.id.substr('rhead'.length);
						$grid.find('.top-mbr' + memberId).each(function(){
							var $this = $(this);
							if (this.getAttribute('data-member') == null) 
								this.setAttribute('data-member',memberId);
							//set parent class to set top position
							this.className = this.className.replace(/\btop-mbr\d+/, 'top-mbr' + parentMemberId);
						});
					});
					
					$img
						.removeClass('x-tree-elbow-minus')
						.addClass('x-tree-elbow-plus');
					$rhead
						.removeClass('tree-expanded')
						.addClass('tree-collapsed');
					methods.set_row_height($rhead, false);
				}
				methods.set_members_top($rhead);
				//throw('ICI C ICI CI ');
				
			},
			
			/* Set row height to see all events */
			set_row_height : function($rhead, expanding){
				var memberId = $rhead[0].id.substr('rhead'.length)//methods.get_member_id($rhead)
				, $grid = $('#grid')
				, $cells = $grid.find('.chip.top-mbr' + memberId)
				, $cells_by_dates = {}
				, $hidden_cells_by_dates = {}
				, max_cells = 1
				, cell_override_offset
				, user_hide_all = methods.is_user_hide_all($grid)
				;
				if (user_hide_all) {
					var hidden_members = {};
					$rhead.nextAll('.rhead.user-hide').each(function(){
						hidden_members[this.id.substr('rhead'.length)] = 1;
					});
				}
				if(expanding){
					$rhead.find('.ico-expand').removeClass('ico-expand').addClass('ico-collapse');
					$rhead.addClass('row-expanded').removeClass('row-collapsed');
					cell_override_offset = options.row_height;
				}
				else {
					$rhead.find('.ico-collapse').removeClass('ico-collapse').addClass('ico-expand');
					$rhead.removeClass('row-expanded').addClass('row-collapsed');
					cell_override_offset = 0.25;
				}
				$cells.each(function(){
					var strDate = methods.get_date(this);
					if (hidden_members
					&& hidden_members[this.getAttribute('data-member')]){
						if ($hidden_cells_by_dates[strDate] === undefined)
							$hidden_cells_by_dates[strDate] = [];
						$hidden_cells_by_dates[strDate].push(this);
						this.style.marginTop = ($hidden_cells_by_dates[strDate].length-1) * 0.25 + 'px';
						return;
					}
					if ($cells_by_dates[strDate] === undefined)
						$cells_by_dates[strDate] = [];
					$cells_by_dates[strDate].push(this);
					
					this.style.marginTop = ($cells_by_dates[strDate].length-1) * cell_override_offset + 'px';
					
					if (max_cells < $cells_by_dates[strDate].length)
						max_cells = $cells_by_dates[strDate].length;
				});
				if(expanding){
					$rhead.attr('r-height', max_cells * cell_override_offset);
					$rhead.css('height', max_cells * cell_override_offset + 'px');
				}
				else {
					$rhead.removeAttr('r-height');
					$rhead.css('height', options.row_height + 'px');
				}
				methods.set_members_top($rhead);
				//throw('IC IIC I CI CI C');
			},
			
			/* Set row height to see all events */
			toggle_rows_height : function(){
				var $this = $(this)
				, $rhead = $this.parents('.rhead:first')
				, expanding = /ico-expand/.test(this.className);
				methods.set_row_height($rhead, expanding);
				throw('IC IIC I CI CI C');
			}
			
			// hides/shows members user choosed to hide
			, toggle_user_view_trash: function(){
				var checked = /\bchecked\b/.test(this.className)
				, $this = $(this)
				, $grid = $('#grid')
				, $table = $this.parents('.coViewBody:first');
				if(checked){
					//show all
					$this.removeClass('checked');
					$table.removeClass('user-view-trash-checked');
				} else {
					//hide checked
					$this.addClass('checked');
					$table.addClass('user-view-trash-checked');
				}
				var $rhead = $table.find('.rhead.user-hide:first');
				if($rhead.length == 0)
					$rhead = $table.find('.rhead:first')
				if($rhead.length > 0)
					methods.set_members_top($rhead);
			}
			
			// define css rules to hide members
			, setMemberHiddenStyle: function($rhead, hidden){
				
				var parentMemberId = $rhead[0].id.substr('rhead'.length)
				, root_depth = parseInt($rhead[0].className.replace(/^.*mbr-depth(\d+).*$/, '$1'))
				, selector = '.user-view-trash-checked [data-member="' + parentMemberId + '"]'
				, styleSheet = getFavoriteStyleSheet();
				
				
				$rhead.nextAll('div.rhead').each(function(){
					var depth = parseInt(this.className.replace(/^.*mbr-depth(\d+).*$/, '$1'));
					if(depth <= root_depth)
						return false;//stop
					var memberId = this.id.substr('rhead'.length);
					selector += ', .user-view-trash-checked [data-member="' + memberId + '"]';
				});
				
				css_rule = getCSSRule(selector, styleSheet);
				if(css_rule){
					styleSheet = css_rule.parentStyleSheet;
					killCSSRule(selector, styleSheet);
				}
				if(hidden)
					css_rule = addCSSRule(selector, 'display: none;', styleSheet);
			}
			
			// hides/shows member row
			, toggle_member_row_trash: function(){
				var $this = $(this)
				, $rhead = $this.parents('.rhead:first')
				, $table = $this.parents('.coViewBody:first')
				, checked = /\buser-hide\b/.test($rhead[0].className)
				, user_hide_all = $table.hasClass('user-view-trash-checked');
				if(checked){
					$rhead.removeClass('user-hide');
				} else {
					$rhead.addClass('user-hide');
				}
				checked = !checked;
				methods.setMemberHiddenStyle($rhead, checked);
				if(user_hide_all)
					methods.set_members_top($table.find('.rhead:first'));
			}
			
		}
		<?php } ?>
		;
		for(var i = 0; i < options.dates.length; i++){
			var strDate = options.dates[i];
			og.ev_cell_dates[strDate] = {day: strDate.substr(8,2), month: strDate.substr(5,2), year: strDate.substr(0,4)};
			var ev_dropzone_allday = new Ext.dd.DropZone('alldayeventowner_' + strDate, {ddGroup:'ev_dropzone_allday'})
			, ev_dropzone_alldaytitle = new Ext.dd.DropZone('alldaycelltitle_' + strDate, {ddGroup:'ev_dropzone_allday'});
			
			if (!options.isGuest)
				document.getElementById('alldayeventowner_' + strDate).onclick = methods.alldayeventowner_onclick;
				
			for(var m = 0; m < options.members.length; m++){
				var div_id = 'h' + strDate + options.members[m];
				
				methods.vy_cell_init(document.getElementById(div_id), strDate);
				if (!options.isGuest) 
					var ev_dropzone = new Ext.dd.DropZone(div_id, {ddGroup:'ev_dropzone'});
			}
		}
		
		//init Drag
		<?php if($createEventDrag_args){
			?>var createEventDrag_args = <?= json_encode($createEventDrag_args)?>;
			for (i = 0; i < createEventDrag_args.length; i++) {	
				og.createYearViewDrag.apply(og, createEventDrag_args[i]);
			}
		<?php }?>
		clearCSSPageRules();
		$(".internalLink, .chip").click(methods.disableEventPropagation);
		$(".chip").mouseup(methods.clearPaintedCells);
		$(".chead-expand").click(methods.chead_expand_onclick);
		$("#rowheaders .rheadtext .ico-expand").click(methods.toggle_rows_height);
		$(".nav-btn").click(methods.navigate);
		$("#rowheaders .x-tree-ec-icon.x-tree-elbow-minus, #rowheaders .x-tree-ec-icon.x-tree-elbow-plus").click(methods.toggle_tree_node);
		$('.user-view .ico-trash').click(methods.toggle_user_view_trash);
		$('.member-tools .ico-trash').click(methods.toggle_member_row_trash);
	})();

	// Top Toolbar	
	ogCalendarUserPreferences = Ext.util.JSON.decode(document.getElementById('hfCalUserPreferences').value);
	og.ogCalTT = new og.CalendarTopToolbar({
		renderTo:'calendarPanelTopToolbar'
	});	
	og.ogCalSecTT = new og.CalendarSecondTopToolbar({
		usersHfId:'hfCalUsers',
		companiesHfId:'hfCalCompanies',
		renderTo: 'calendarPanelSecondTopToolbar'
	});

	// Mantain the actual values after refresh by clicking Calendar tab.
	var dtv = new Date('<?php echo $date_request->format(FORMAT_DATE_KEY) ?>');
	og.calToolbarDateMenu.picker.setValue(dtv);

	if (Ext.isIE) document.getElementById('ie_scrollbar_adjust').style.width = '15px';
	
	// resize grid
	function resizeGridContainer(e, id) {
		maindiv = document.getElementById('cal_main_div');
		if (maindiv == null) {
			og.removeDomEventHandler(window, 'resize', id);
		} else {
			var divHeight = maindiv.offsetHeight;
			var tbarsh = Ext.get('calendarPanelSecondTopToolbar').getHeight() + Ext.get('calendarPanelTopToolbar').getHeight();
			divHeight = divHeight - tbarsh - <?php echo (PX_HEIGHT + $allgridHeight); ?>;
			document.getElementById('gridcontainer').style.height = divHeight + 'px';
		}
	}
	resizeGridContainer();
	if (Ext.isIE) {
		og.addDomEventHandler(document.getElementById('cal_main_div'), 'resize', resizeGridContainer);
	} else {
		og.addDomEventHandler(window, 'resize', resizeGridContainer);
	}
	// init tooltips
	//ED150221 Ext.QuickTips.init();
        
        Ext.extend(og.EventRelatedPopUp, Ext.Window, {
                accept: function() {
                        var action = $("#action_related").val();
                        var opt = $("#<?php echo $genid?>type_related").val();
                        og.openLink(og.getUrl('event', action, {ids: og.getSelectedEventsCsv(), options:opt}));
                        this.close();
                }
        });
        
        function selectEventRelated(val){
            $("#<?php echo $genid?>type_related").val(val);
        }
</script>
<style>
	.vy-cell {
		width:<?php echo $date_width ?>px;
		max-width:<?php echo $date_width * 8 ?>px;
		height:<?=PX_HEIGHT?>px;
		position:absolute;
		z-index: 90;
		
	}
	.vy-cell.cell-day {
		width:<?php echo $date_width * 2 ?>px;
		
	}
	.vy-cell.cell-day.day-sunday {
		width:<?php echo $date_width ?>px;
		
	}
	.vy-today {
		background-color:#efefa8;
		opacity:0.9;
		z-index:0;		
	}
	.chead {
		text-align:center;
		position:absolute;
		top:0%;
		height:100%;
		width: <?= $date_width ?>px; 
	}
	.chead.cell-day {
		width: <?= $date_width * 2 ?>px; 
	}
	.chead.cell-day.day-sunday {
		width: <?= $date_width ?>px; 
	}
	.rhead {
		height: <?php echo PX_HEIGHT-1?>px; top: 0ex;
		background: #E8EEF7 none repeat scroll 0%;
		border-top:1px solid #DDDDDD;
		left:0pt;
		width: 100%;
	}
	.rheadtext {
		text-align:left;padding-right:2px; padding-left:2px; vertical-align: middle; height: 100%;
	}
	.rheadtext > div:first-of-type {
		display: inline-block; text-align: right; vertical-align: middle; height: 100%;
	}
	.rheadtext > div:first-of-type > img {
		position: relative;
		left: 4px;
	}
	.rheadtext > div:first-of-type + div {
		display: inline-block; vertical-align: middle;
		overflow: hidden; -o-text-overflow: ellipsis; /* pour Opera 9 */ text-overflow: ellipsis; /* pour le reste du monde */;
		height:100%; line-height: 14px;
	}
	.rhead.parent-tree-collapsed {
		display: none;
	}
	.rheadtext > .member-tools {
		float: right;
		height: 100%;
		width: 16px;
		/*display: none;*/
	}
	.rheadtext > .member-tools * {
		opacity: 0.3;
	}
	.rhead.user-hide .rheadtext > .member-tools button.ico-trash {
		opacity: 1;
	}
	.rheadtext > .member-tools > * {
		/*background-position: 0px <?php echo (PX_HEIGHT-16)/2 ?>px;*/
		margin-bottom: 4px;
		background-repeat: no-repeat;
		cursor: pointer;
	}
	.rheadtext:hover > .member-tools {
		background-color: #DDDDDD;
	}
	.rheadtext:hover > .member-tools * {
		opacity: 1;
	}
	
	.member-tools .ico-collapse {
		height: 100%;
	}
	
	#gridcontainercell {
		height: 100%;
		width:100%;
		position:relative;
	}
	#grid {
		height: 100%;
		background-color:#fff;
		position:relative;
	}
	.hrule {
		height:0px; z-index:1; position:absolute; left:0px; width:100%;
	}
	
	.mbr-child {
		border-top:1px solid #D3D3D3;
	}
	.mbr-sibling {
		border-top:1px dotted #D3D3D3;
	}
	.mbr-ancestor {
		border-top:1px dotted #DDDDDD;
	}
	.chip {
		position: absolute;
		z-index: 120;
		height: <?php echo PX_HEIGHT - 1 ?>px;
		width:<?php echo $date_width - 0.25 ?>px;
	}
	.chip.h-expanded {
		width:<?php echo $date_width * 2 - 0.25 ?>px;
	}
	.chip:hover {
		min-width: <?php echo $date_width * 4 ?>px;
		max-width:<?php echo $date_width * 8 ?>px;
		height: auto;
		z-index: 121;
	}
	.chipbody {
		height: 89%;
	}
	.chip:hover .chipbody {
		height: <?php echo 1.5 * PX_HEIGHT - 1 ?>px;
	}
	.chipbody > div {
		overflow:hidden;height:100%;border-left: 1px solid;border-right: 1px solid;
	}
	.cell-info2 {
		font-size:10px;
	}
	.chip:hover .cell-info2 {
		font-size: 12px;
		line-height: 12px;
	}
	#gridcontainer{
		background-color:#fff; position:relative; overflow-x:hidden; overflow-y:scroll; height:504px;
	}
	#gridcontainernav {
		position: relative;
		overflow: hidden;
		height: 100%;
	}
	#calowner{
		display:block; width:100%;
	}
	#calowner > table{
		table-layout: fixed; width: 100%; height: 100%;
	}
	/*#r-bottom {
		top: <?php echo $grid_height?>px; height:0px; z-index:1; position:absolute; left:0px;
		border-top:1px solid #D3D3D3;;width:100%;
	}*/
	#rowheaders { top: 0pt; left: 0pt; }
	#eventowner { z-index: 102; }
	#rowheadcell { width: <?=$members_column_width?>px; }
	#allDayGrid {
		height: 100%; 
		margin-right:15px;
		position:relative;
		margin-left:0;
	}
	#allDayGridContainer{
		background:#E8EEF7;
		margin-left:<?=$members_column_width?>px;
		height: 41px;
		margin-bottom: 5px;
		position:relative;
	}
	<?php /*double traits verticaux*/ ?>
	.vy-vd {
		height: <?=$grid_height * 2 /* hack*/ ?>px;
		border-left:3px double #DDDDDD !important; position:absolute;
		width:3px;z-index:110;
	}
	.t1 {	margin:0px 2px 0px 2px;height:0px; border-bottom:1px solid; }
	.t2 {	margin:0px 1px 0px 1px;height:1px; border-left:1px solid;border-right:1px solid; }
	.b1 { margin:0px 1px 0px 1px;height:1px; border-left:1px solid;border-right:1px solid; }
	.b2 { margin:0px 2px 0px 2px;height:0px; border-top:1px solid; }
	.allDayCell {
		height: 100%;border-left:3px double #DDDDDD !important; position:absolute;
		width:3px;z-index:110;background:#E8EEF7;top:0%;
	}
	.allDayCell + div {
		width: <?php echo $date_width ?>px;
		position:absolute;
		top: 12px;
		height: 42px;
	}
	
	.chead-expand {
		width: <?php echo $date_width + 2 ?>px;
		top: 26px;
		position:absolute; 
		text-align: right;
		z-index: 121; 
	}
	.chead-expand > img {
		cursor: pointer !important;
	}
	
	.nav-btn {
		position: absolute;
		top: 0;
		background-color: rgb(79, 62, 62);
		height: 42px;
		z-index: 122;
		width: 24px;
		cursor: pointer;
		opacity: 0.7;
	}
	.nav-btn:hover { background-color: rgb(109, 92, 92); opacity: 0.9; }
	.nav-btn.btn-right { right: -0px; }
	.nav-toolbar.toolbar-left {
		left: <?= - $members_column_width - 24 ?>px;
		width: <?=$members_column_width?>px;
		background-position: right;
		opacity: 1;
		text-align: right;
		background-color: #E8EEF7;
		/*display: none;*/
	}
	.nav-btn.btn-left {
		right: -24px;
		opacity: 0.4;
		/*display: none;*/
	}
	
	.internalLink {
		font-size:93%; line-height: 100%; overflow:hidden;
	}
	
	.cell-day {
		background-color: rgb(227, 233, 242);
		opacity: 0.5;
	}
	.chead.cell-day {
		opacity: 0.9;
	}
	.cell-day.day-sunday {
		background-color: rgb(228, 215, 215);
	}
	
	.chipbody .permission_group.db-ico {
		float: right;
		border: 1px dashed;
		border-radius: 2px;
	}
	
	/* masquage/affichage des lignes en préférence de l'utilisateur */
	.user-view {
		display: inline-block;
		height: 100%;
		padding-top: 1em;
		margin-right: 8px;
		width: 90%;
		padding-left: 3em;
		text-align: left;
		vertical-align: bottom;
	}
	.user-view .db-ico {
		height: 24px;
		width: 22px;
	}
	.user-view-trash-checked .user-view .ico-trash {
		border-style: inset;
	}
	.rhead.user-hide .member-tools .ico-trash {
		/*visibility: hidden;*/
		border-style: inset;
	}
	<?php
	
	for($depth = 0; $depth <= $max_depth - $min_depth; $depth++){
	?> .mbr-depth<?=$depth?> > div > div:first-of-type { width: <?= $depth * 14 + 32 ?>px; }
	.mbr-depth<?=$depth?> > div > div:first-of-type + div { max-width: <?= $members_column_width - ($depth * 14 + 32) - 8 - 16 ?>px; }
<?php	 }
	
	foreach($dates_left as $strDate => $left){
	?> .cell-<?=$strDate?> { left: <?=$left?>px; }
<?php	 }
	
	?> .top-mbr0 {	top: 0%; }
<?php
	
	foreach($members_top as $memberId => $top){
	?> .top-mbr<?=substr($memberId, 1)?> {	top: <?=$top?>px; }
<?php	}
	?>
</style>
<?php
//die( ob_get_contents() );
?>