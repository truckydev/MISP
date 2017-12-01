<div class = "index">
	<h2>Statistics</h2>
	<?php
		echo $this->element('Users/statisticsMenu');
	?>
	<p>A toplist of the top sources for the sightings of your organisation.</p>
	<table class="table table-striped table-hover table-condensed" style="display:block; overflow-y:auto;width:800px;">
	<tr>
		<th>Source</th>
		<th>#Entries</th>
		<th>#Sighting</th>
		<th>#False-positive</th>
		<th>Expiration</th>
		<th>period-of-interest-from</th>
		<th>period-of-interest-until</th>
	</tr>
	<?php
		$count = 0;
		foreach ($toplist as $source => $total):
			if ($count > 9) break;
	?>
			<tr>
				<td style="width:14%;"><?php echo empty($source) ? 'Undefined' : h($source);?></td>
				<td style="width:14%;"><?php echo h($total);?></td>
				<td style="width:14%;">
					<?php
						if (isset($data[$source]['sighting'])):
					?>
							<a href="<?php echo $baseurl; ?>/events/index/searcheventid:<?php echo h(implode('|', $eventids[$source]['sighting'])); ?>"><?php echo h($data[$source]['sighting']); ?></a>
					<?php
						else:
							echo '0';
						endif;
					?>
				</td>
				<td style="width:14%;">
					<?php
						if (isset($data[$source]['false-positive'])):
					?>
							<a href="<?php echo $baseurl; ?>/events/index/searcheventid:<?php echo h(implode('|', $eventids[$source]['false-positive'])); ?>"><?php echo h($data[$source]['false-positive']); ?></a>
					<?php
						else:
							echo '0';
						endif;
					?>
				</td>
				<td style="width:14%;">
					<?php
						if (isset($data[$source]['expiration'])):
					?>
							<a href="<?php echo $baseurl; ?>/events/index/searcheventid:<?php echo h(implode('|', $eventids[$source]['expiration'])); ?>"><?php echo h($data[$source]['expiration']); ?></a>
					<?php
						else:
							echo '0';
						endif;
					?>
				</td>
				<td style="width:20%;">
					<?php
						if (isset($data[$source]['period-of-interest-from'])):
					?>
							<a href="<?php echo $baseurl; ?>/events/index/searcheventid:<?php echo h(implode('|', $eventids[$source]['period-of-interest-from'])); ?>"><?php echo h($data[$source]['period-of-interest-from']); ?></a>
					<?php
						else:
							echo '0';
						endif;
					?>
				</td>
				<td style="width:20%;">
					<?php
						if (isset($data[$source]['period-of-interest-until'])):
					?>
							<a href="<?php echo $baseurl; ?>/events/index/searcheventid:<?php echo h(implode('|', $eventids[$source]['period-of-interest-until'])); ?>"><?php echo h($data[$source]['period-of-interest-until']); ?></a>
					<?php
						else:
							echo '0';
						endif;
					?>
				</td>

			</tr>
	<?php
			$count++;
		endforeach;
	?>
	</table>
</div>
<?php
	echo $this->element('side_menu', array('menuList' => 'globalActions', 'menuItem' => 'statistics'));
?>
<script type="text/javascript">
$(document).ready(function () {
	loadSightingsData();
});
</script>
