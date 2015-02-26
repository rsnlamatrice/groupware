

<div class="widget-emails widget dashUnreadEmails">

	<div style="overflow: hidden;" class="widget-header dashHeader" onclick="og.dashExpand('<?php echo $genid?>');">
		<?php echo (isset($widget_title)) ? $widget_title : lang("unread emails");?>
		<div class="dash-expander ico-dash-expanded" id="<?php echo $genid; ?>expander"></div>
	</div>
	
	<div class="widget-body" id="<?php echo $genid; ?>_widget_body">
		<ul>
			<?php
			$count = 0;
			$style = '';
			$row_cls = "";
			foreach ($emails as $k => $email): /* @var $email MailContent */
				$crumbOptions = json_encode($email->getMembersToDisplayPath());
				$crumbJs = " og.getCrumbHtml($crumbOptions) ";
				if ($count >= 5) $style = 'display:none;';
			?>
				<li id="<?php echo "email-".$email->getId()?>" class="email-row ico-email <?php echo $row_cls ?>" style="<?php echo $style;?>">
					<a href="<?php echo $email->getViewUrl() ?>">
						<span class="bold"><?php echo clean($email->getSubject());?>: </span>
						<span class="breadcrumb"></span>
						<br />
						<span><?php echo clean($email->getFrom());?></span><span class="desc" style="float:right;"><?php echo friendly_date($email->getSentDate())?></span>
					</a>
					<script>
						var crumbHtml = <?php echo $crumbJs?> ;
						$("#email-<?php echo $email->getId()?> .breadcrumb").html(crumbHtml);
					</script>
				</li>
				<?php $row_cls = $row_cls == "" ? "dashAltRow" : "";
				$count++;
				?>
			<?php endforeach; ?>
		</ul>
		<?php if ($count > 5) { ?>
		<div style="text-align:right;"><a id='showlnk-email' href="#" onclick="og.showHideWidgetMoreLink('.email-row.ico-email','-email',true)"><?php echo lang("show more") ?></div>
		<?php }?>
		<div class="x-clear"></div>
		<div class="progress-mask"></div>
	</div>
	
</div>
