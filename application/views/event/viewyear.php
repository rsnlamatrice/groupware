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

$max_events_to_show = user_config_option('displayed events amount');
if (!$max_events_to_show) $max_events_to_show = 15;
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
	$members = ObjectMembers::getMembersByObjects(array_keys($events_assoc), $members_by_objects, active_context_members(false));
	//references dates and members events
	foreach ($events as $eventId => $event) {
		$member = $members_by_objects[$eventId];
		if(!$member){
			//var_dump($event->getId(), $members_by_objects[$event->getId()]);
			continue;
		}
		
		//date du lundi
		if(is_a($event, 'ProjectEvent')){
			$date = $event->getStart()->getMondayOfWeek();
		}
		elseif(is_a($event, 'ProjectTask')){ 
			$date = $event->getStartDate()->getMondayOfWeek();
		}
		else {
			echo 'event de type inconnu : '; var_dump($event);
			continue;//TODO ?!
		}
		
		$strDate = $date->format(FORMAT_DATE_KEY);
		if(!isset($dates[$strDate]))
			$dates[$strDate] = array();
		$memberId = $member->getId();
		if(!isset($dates[$strDate]['_'.$memberId]))
			$dates[$strDate]['_'.$memberId] = array();
		//else
		//	var_dump($strDate, count($dates[$strDate]['_'.$memberId]));
		$dates[$strDate]['_'.$memberId][$eventId] = $event;
	}
//var_dump($date_start->format('d/m/Y'), $date_end->format('d/m/Y'), '$members', count($members), '$events', count($events),'$tmp_tasks', count($tmp_tasks), active_context_members(false));
	
	define('PX_HEIGHT', count($members) < 10 ? 68 : 42);
	define('PIX_CELL_OVER', 2);
	
	$alldaygridHeight = PX_HEIGHT;
	$members_top = array();
	
	$users_array = array();
	$companies_array = array();
	foreach($users as $u)
		$users_array[] = $u->getArrayInfo();
	foreach($companies as $company)
		$companies_array[] = $company->getArrayInfo();
	
	/*ED150212*/
	$members_column_width = 170;
	$min_depth = 0;
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
<div id="<?php echo $genid."view_calendar"?>">  
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
		<td class="coViewBody" style="padding:0px;height:100%;" colspan=2>
		<div id="chrome_main2" style="width:100%; height:100%">
		<div id="allDayGrid" class="inset grid">
		<?php					
			$week_width = 48/*px*/;//100/(DateTimeValue::FormatTimeDiff($date_end, $date_start, 'd')/7);
			//if($width_percent < 3)
			//	$width_percent = 3;
			$current_week_left = 0;
			$date_end_timespamp = $date_end->getTimestamp();
			$working_date = new DateTimeValue( $date_start->getTimestamp() );
			$dates_left = array();
			//weeks loop
			do {	
				$week = $working_date->format('w');
				$day_of_week = $working_date->getDayOfWeek();
				$day_of_month = $working_date->getDay();
				$strDate = $working_date->format(FORMAT_DATE_KEY);
				// see what type of day it is
				$today_text = "";			
				if($working_date->isToday()){
					$daytitle = 'todaylink';
					$today_text = "Today ";
				}else $daytitle = 'daylink';
				
				// if weekends override do this
				if( !user_config_option("start_monday") AND ($day_of_week==0 OR $day_of_week==6) AND $day_of_month <= $lastday AND $day_of_month >= 1){
					$daytype = "weekend";
				}elseif( user_config_option("start_monday") AND ($day_of_week==5 OR $day_of_week==6) AND $day_of_month <= $lastday AND $day_of_month >= 1){
					$daytype = "weekend";
				}elseif($day_of_month <= $lastday AND $day_of_month >= 1){
					$daytype = "weekday";
				}else{
					$daytype = "weekday_future";
				}
		
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
				<div id="alldaycelltitle_<?php echo $strDate ?>" class="chead cell-<?=$strDate?> cell-mbr0 <?=$currentMonday == $strDate ? 'vy-today' : 'cheadNotToday'?> x-tree-lines">
					<span id="chead<?php echo $strDate ?>">
						<a class="internalLink" href="<?php echo $p; ?>"><?php echo $dtv_temp->format('d/m') . ' sem.' . $dtv_temp->format('W'); ?></a>
					</span>
				</div>
				<div class="chead-extend x-tree-arrows cell-<?=$strDate?>">
					<img class="x-tree-elbow-plus" src="s.gif"/>
				</div>
				<div id="allDay<?php echo $strDate ?>" class="allDayCell cell-<?php echo $strDate ?>"></div>

				<div id="alldayeventowner_<?php echo $strDate ?>" class="cell-<?php echo $strDate ?>" data-date="<?php $dtv_temp->format(FORMAT_DATE_KEY)?>"></div>
		<?php
				$dates_left[$strDate] = $current_week_left;
				
				$current_week_left += $week_width;
				
				//ED150211
				//1 week after
				$working_date->add('d', 7);
			}
			while($working_date->getTimestamp() <= $date_end_timespamp);
		?>
	</div>
<?php if(true){?>
	<div id="gridcontainer" class="toprint">	
			<div id="calowner">  
				<table cellspacing="0" cellpadding="0" border="0">
					<tr>
						<td id="rowheadcell" style="width: <?=$members_column_width?>px;">
							<div id="rowheaders">										
							<?php
								$nMember = 0;
								$grid_height = 0;
									
								// Context headers column 
								foreach ($members as $memberId => $member){
									if(!is_object($member)){
										var_dump('$member ! object', $member);
										continue;
									}
									if($min_depth === 0)//first
										$min_depth = $member->getDepth();
									$members_top['_'.$memberId] = $grid_height;
							?>
<div class="rhead" id="rhead<?php echo $memberId?>" >
<div class="rheadtext  x-tree-lines">
	<div style="width: <?= ($member->getDepth() - $min_depth) * 14 + 36 ?>px;">
		<?php if($member->getHasChild()){
			?><img src="s.gif" class="x-tree-ec-icon x-tree-elbow-minus"/><?php
		 }
		?><img src="s.gif" class="x-tree-node-icon ico-color<?=$member->getColor()?>"/>
	</div>
	<div style="max-width: <?= $members_column_width - (($member->getDepth() - $min_depth) * 14 + 36) - 8 ?>px;">
		<?php echo $member->getName() ?>
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
							<?php /* event cells */
							if(true){?>
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
<div id="r<?php echo $memberId?>" class="hrule <?php echo $attr_class?> cell-mbr<?php echo $memberId?>"></div>
<?php
									$prev_depth = $member->getDepth();
								}
?>
<div id="r-bottom" class="hrule"></div>

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
<div class="vy-cell cell-<?=$strDate?> cell-mbr<?=$memberId?><?= $strDate == $currentMonday ? ' vy-today' : ''?>" id="<?php echo $div_id?>"></div>
<?php
?>

<?php									}

									 ?><div id="vd<?php echo $strDate ?>" class="cell-<?=$strDate?> vy-vd"></div>
<?php									if($dates[$strDate]){
										$cells = array();
										
										$occup = array(); //keys: memberid - pos
										foreach ($dates[$strDate] as $parentKey => $memberTasks)
										foreach($memberTasks as $event_id => $event){
											
											if(isset($cells[$parentKey]))
												$cells[$parentKey]++;
											else
												$cells[$parentKey] = 0;
												
											if(is_a($event, 'ProjectEvent')){
												$eventDate = $event->getStart()->getMondayOfWeek();
											}
											elseif(is_a($event, 'ProjectTask')){ 
												$eventDate = $event->getStartDate()->getMondayOfWeek();
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

											$width = $week_width;
											$left = $current_week_left + 0.25 + $cells[$parentKey] * PIX_CELL_OVER;
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

											?><script>
												addTip('w_ev_div_' + '<?php echo $event->getId() . $id_suffix ?>', <?php echo json_encode(clean($event->getObjectName())) ?>
												       , <?php echo json_encode($tipBody);?>);
											</script>
<?php
											$bold = "bold";
											if ($event instanceof Contact || $event->getIsRead(logged_user()->getId())){
												$bold = "normal";
											}

						?><div id="w_ev_div_<?php echo $event->getId() . $id_suffix?>" class="chip" <?php
							?> style="top: <?php echo $top?>px; left: <?php echo $left?>px; width: <?php echo $width?>px;height:<?php echo $height ?>px;" <?php
							?> data-date="<?=$real_start->format(FORMAT_DATE_KEY)?>" data-member="<?=$memberId?>">
						<div class="t1 <?php echo $ws_class ?>" style="<?php echo $ws_style ?>;border-color:<?php echo $border_color ?>"></div>
						<div class="t2 <?php echo $ws_class ?>" style="<?php echo $ws_style ?>;border-color:<?php echo $border_color ?>"></div>
						<div id="inner_w_ev_div_<?php echo $event->getId() . $id_suffix?>" class="chipbody edit og-wsname-color-<?php echo $ws_color?>" style="height:<?php echo $height - 4 ?>px;">
						<div style="border-color:<?php echo $border_color ?>;"><?php
							?><table style="width:100%;"><tr><td><?php
								if (false && $event instanceof ProjectEvent) {
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

									$label = $real_start->format('j M');
									?><span name="w_ev_div_<?php echo $event->getId() . $id_suffix?>_info" style="font-weight:<?php echo $bold ?>;"><?= $label ?></span><?php

									$label = $subject; 
									?><div name="w_ev_div_<?php echo $event->getId() . $id_suffix?>_info2" style="font-size: 10px;"><?= $label ?></div><?php
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
						if (false && $event instanceof ProjectEvent) {
						//TODO vertical
						?>
						<script>
							<?php if (!$end_modified) { ?> 
							og.setResizableEvent('w_ev_div_<?php echo $event->getId() . $id_suffix?>', '<?php echo $event->getId()?>'); // Resize
							<?php } ?>
							<?php $is_repetitive = $event->isRepetitive() ? 'true' : 'false'; ?>
							<?php if (!logged_user()->isGuest()) { ?>
							og.createEventDrag('w_ev_div_<?php echo $event->getId() . $id_suffix?>', '<?php echo $event->getId()?>', <?php echo $is_repetitive ?>, '<?php echo $event_start->format('Y-m-d H:i:s') ?>', 'event', false, 'ev_dropzone'); // Drag
							<?php } ?>
						</script>
						<?php }
										}//foreach
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
									</td><td id="ie_scrollbar_adjust" style="width:0px;"></td>
								</tr>
							</table>
						</div><!-- calowner -->
					<?php }?>
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
	<?php	/* ED150219 */
		?>var dates = <?= json_encode($added_dates) ?>
		, members = <?= json_encode(array_keys($members_top)) ?>
		<?php if (!logged_user()->isGuest()) {
		?>, div_events = {
			onmouseover : function(){
				if (!og.selectingCells) og.overCell(this.id); else og.paintSelectedCells(this.id);
			},
			onmouseout : function(){
				if (!og.selectingCells) og.resetCell(this.id);
			},
			onmousedown : function(){
				var strDate = this.getAttribute('data-date');
				og.selectStartDateTime(strDate.substring(8,2), strDate.substring(5,2), strDate.substring(0,4), 0, 0);
				og.resetCell(this.id);
				og.paintingDay = strDate;
				og.paintSelectedCells(this.id);
			},
			onmouseup : function(){
				var strDate = this.getAttribute('data-date');
				og.showEventPopup(strDate.substring(8,2), strDate.substring(5,2), strDate.substring(0,4), 0, 0, <?php echo ($use_24_hours ? 'true' : 'false'); ?>
				     , strDate, '<?php echo $genid?>'
				     , '<?php echo ProjectEvents::instance()->getObjectTypeId()?>');
			},
			alldayeventowner_onclick : function(){
				var strDate = this.getAttribute('data-date');
				og.showEventPopup(strDate.substring(8,2), strDate.substring(5,2), strDate.substring(0,4), -1, -1, <?php echo ($use_24_hours ? 'true' : 'false'); ?>
					, strDate.substring(8,2) + '/' + strDate.substring(5,2) + '/' + strDate.substring(0,4)
					, '<?php echo $genid?>', '<?php echo ProjectEvents::instance()->getObjectTypeId()?>');
			},
			disableEventPropagation : function(){
				og.disableEventPropagation(event);
			},
			clearPaintedCells : function(){
				og.clearPaintedCells();
			},
			chead_extend_onclick : function(event){
				alert('Enlarge your column');
			}
		}
		<?php } ?>
		;
		for(var i = 0; i < dates.length; i++){
			var strDate = dates[i];
			og.ev_cell_dates[strDate] = {day: strDate.substring(8,2), month: strDate.substring(5,2), year: strDate.substring(0,4)};
			var ev_dropzone_allday = new Ext.dd.DropZone('alldayeventowner_' + strDate, {ddGroup:'ev_dropzone_allday'})
			, ev_dropzone_alldaytitle = new Ext.dd.DropZone('alldaycelltitle_' + strDate, {ddGroup:'ev_dropzone_allday'});
			
			<?php if (!logged_user()->isGuest()) { ?>
				document.getElementById('alldayeventowner_' + strDate).onclick = div_events.alldayeventowner_onclick;
			<?php } ?>
				
			for(var m = 0; m < members.length; m++){
				var div_id = 'h' + strDate + members[m];
				
				<?php if (!logged_user()->isGuest()) {
				?>	var dom = document.getElementById(div_id);
					dom.setAttribute('data-date', strDate);
					dom.onmouseover = div_events.onmouseover;
					dom.onmouseout = div_events.onmouseout;
					dom.onmousedown = div_events.onmousedown;
					dom.onmouseup = div_events.onmouseup;
				<?php } ?>
	
				var ev_dropzone = new Ext.dd.DropZone(div_id, {ddGroup:'ev_dropzone'});
			}
		}
		
		$(".internalLink, .chip").click(div_events.disableEventPropagation);
		$(".chip").mouseup(div_events.clearPaintedCells);
		$(".chead-extend").click(div_events.chead_extend_onclick);
						
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
	var dtv = new Date('<?php echo $month.'/'.$day.'/'.$year ?>');
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
			divHeight = divHeight - tbarsh - <?php echo (PX_HEIGHT + $alldaygridHeight); ?>;
			document.getElementById('gridcontainer').style.height = divHeight + 'px';
		}
	}
	resizeGridContainer();
	if (Ext.isIE) {
		og.addDomEventHandler(document.getElementById('cal_main_div'), 'resize', resizeGridContainer);
	} else {
		og.addDomEventHandler(window, 'resize', resizeGridContainer);
	}
	
<?php if ($drawHourLine) { ?>
	og.preferences['start_monday'] = <?php echo json_encode(user_config_option('start_monday')) ?>;
	og.startLocaleTime = new Date('<?php echo $today->format('m/d/Y H:i:s') ?>');
	og.startLineTime = null;
	if (og.preferences['start_monday'] == 1) {
		var today_d = og.startLocaleTime.format('N') - 1;
	} else {
		var today_d = og.startLocaleTime.format('w');
	}
	og.drawCurrentHourLine(today_d, 'w_');
<?php } ?>
	// init tooltips
	Ext.QuickTips.init();
        
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
		width:<?php echo $week_width ?>px;
		height:<?=PX_HEIGHT?>px;
		position:absolute;
		z-index: 90;
		
	}
	.vy-today {
		background-color:#efefa8;
		opacity:0.9;
		filter: alpha(opacity = 90);
		z-index:0;		
	}
	.chead {
		text-align:center;
		position:absolute;
		top:0%;
		height:100%;
		width: <?= $week_width ?>px; 
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
	#gridcontainercell {
		width:100%;
		position:relative;
	}
	#grid {
		height: 100%;
		background-color:#fff;
		position:relative;
	}
	.hrule {
		height:0px; z-index:1; position:absolute; left:0px;width:100%;
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
		position: absolute; z-index:120;
	}
	.chipbody > div {
		overflow:hidden;height:100%;border-left: 1px solid;border-right: 1px solid;
	}
	#gridcontainer{
		background-color:#fff; position:relative; overflow-x:hidden; overflow-y:scroll; height:504px;
	}
	#calowner{
		display:block; width:100%;
	}
	#calowner > table{
		table-layout: fixed; width: 100%; height: 100%;
	}
	#r-bottom {
		top: <?php echo $grid_height?>px; height:0px; z-index:1; position:absolute; left:0px;
		border-top:1px solid #D3D3D3;;width:100%;
	}
	#rowheaders { top: 0pt; left: 0pt; }
	#eventowner { z-index: 102; }
	#allDayGrid { height: <?php echo $alldaygridHeight ?>px; margin-bottom: 5px;
		background:#E8EEF7;margin-right:15px;
		margin-left:<?=$members_column_width?>px;position:relative;
	}
	.vy-vd {
		height: <?=$grid_height?>px;border-left:3px double #DDDDDD !important; position:absolute;
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
		width: <?php echo $week_width ?>px;
		position:absolute;
		top: 12px;
		height: <?php echo $alldaygridHeight ?>px;
	}
	
	.chead-extend {
		width: <?php echo $week_width + 2 ?>px;
		top: 26px;
		position:absolute; 
		text-align: right;
		z-index: 121; 
	}
	.chead-extend > img {
		cursor: pointer !important;
	}
	
	.internalLink {
		font-size:93%; line-height: 100%; overflow:hidden;
	}
	<?php
	foreach($dates_left as $strDate => $left){
	?> .cell-<?=$strDate?> { left: <?=$left?>px; }
	<?php }
	
	?> .cell-mbr0 {	top: 0%; }<?php
	
	foreach($members_top as $memberId => $top){
	?> .cell-mbr<?=substr($memberId, 1)?> {	top: <?=$top?>px; }
	<?php }
	?>
</style>
<?php
//die( ob_get_contents() );
?>