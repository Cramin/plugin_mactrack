<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2009 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

$guest_account = true;

chdir('../../');
include("./include/auth.php");
include_once("./include/global_arrays.php");
include_once("./plugins/mactrack/lib/mactrack_functions.php");

define("MAX_DISPLAY_PAGES", 21);

load_current_session_value("report", "sess_mactrack_view_report", "ips");

if (isset($_REQUEST["export_ips_x"])) {
	mactrack_view_export_ip_ranges();
}else{
	$title = "Device Tracking - Site IP Range Report View";
	include_once("./include/top_graph_header.php");
	mactrack_view_ip_ranges();
	include_once("./include/bottom_footer.php");
}

function mactrack_view_export_ip_ranges() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("site_id"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up report string */
	if (isset($_REQUEST["report"])) {
		$_REQUEST["report"] = sanitize_search_string(get_request_var("report"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_mactrack_view_ips_current_page", "1");
	load_current_session_value("site_id", "sess_mactrack_view_ips_site_id", "-1");
	load_current_session_value("sort_column", "sess_mactrack_device_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_device_sort_direction", "ASC");

	$sql_where = "";

	$ip_ranges = mactrack_view_get_ip_range_records($sql_where, 0, FALSE);

	$xport_array = array();

	array_push($xport_array, '"site_id","site_name","ip_range",' .
			'"ips_current","ips_current_date","ips_max","ips_max_date"');

	if (is_array($ip_ranges)) {
		foreach($ip_ranges as $ip_range) {
			array_push($xport_array,'"'   .
				$ip_range['site_id']     . '","' . $ip_range['site_name']        . '","' .
				$ip_range['ip_range']    . '","' .
				$ip_range['ips_current'] . '","' . $ip_range['ips_current_date'] . '","' .
				$ip_range['ips_max']     . '","' . $ip_range['ips_max_date']     . '"');
		}
	}

	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=cacti_ip_range_xport.csv");
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_view_get_ip_range_records(&$sql_where, $row_limit, $apply_limits = TRUE) {
	if ($_REQUEST["site_id"] != "-1") {
		$sql_where = "WHERE mac_track_ip_ranges.site_id='" . $_REQUEST["site_id"] . "'";
	}else{
		$sql_where = "";
	}

	$ip_ranges = "SELECT
		mac_track_sites.site_id,
		mac_track_sites.site_name,
		mac_track_ip_ranges.ip_range,
		mac_track_ip_ranges.ips_max,
		mac_track_ip_ranges.ips_current,
		mac_track_ip_ranges.ips_max_date,
		mac_track_ip_ranges.ips_current_date
		FROM mac_track_ip_ranges
		INNER JOIN mac_track_sites ON (mac_track_ip_ranges.site_id=mac_track_sites.site_id)
		$sql_where
		ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

	if ($apply_limits) {
		$ip_ranges .= " LIMIT " . ($row_limit*($_REQUEST["page"]-1)) . "," . $row_limit;
	}

	return db_fetch_assoc($ip_ranges);
}

function mactrack_view_ip_ranges() {
	global $title, $colors, $config, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("site_id"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up report string */
	if (isset($_REQUEST["report"])) {
		$_REQUEST["report"] = sanitize_search_string(get_request_var("report"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if any of the settings changed, reset the page number */
	$changed = 0;
	$changed += mactrack_check_changed("site_id", "sess_mactrack_view_ips_site_id");
	if ($changed) {
		$_REQUEST["page"] = "1";
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("report", "sess_mactrack_view_report", "ips");
	load_current_session_value("page", "sess_mactrack_view_ips_current_page", "1");
	load_current_session_value("site_id", "sess_mactrack_view_ips_site_id", "-1");
	load_current_session_value("rows", "sess_mactrack_view_ips_rows", "-1");
	load_current_session_value("sort_column", "sess_mactrack_device_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_device_sort_direction", "ASC");

	if ($_REQUEST["rows"] == -1) {
		$row_limit = read_config_option("num_rows_mactrack");
	}elseif ($_REQUEST["rows"] == -2) {
		$row_limit = 999999;
	}else{
		$row_limit = $_REQUEST["rows"];
	}

	mactrack_tabs();

	mactrack_view_header();

	include("./plugins/mactrack/html/inc_mactrack_view_ips_filter_table.php");

	mactrack_view_footer();

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$sql_where = "";

	$ip_ranges = mactrack_view_get_ip_range_records($sql_where, $row_limit);

	$total_rows = db_fetch_cell("SELECT
		COUNT(mac_track_ip_ranges.ip_range)
		FROM mac_track_ip_ranges
		INNER JOIN mac_track_sites ON (mac_track_ip_ranges.site_id=mac_track_sites.site_id)
		$sql_where");

	/* generate page list */
	$url_page_select = str_replace("&page", "?page", get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $_REQUEST["rows"], $total_rows, "mactrack_view.php"));

	if (isset($config["base_path"])) {
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='13'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_view.php?page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . ($total_rows == 0 ? "None" : (($_REQUEST["rows"]*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $_REQUEST["rows"]) || ($total_rows < ($_REQUEST["rows"]*$_REQUEST["page"]))) ? $total_rows : ($_REQUEST["rows"]*$_REQUEST["page"])) . " of $total_rows [$url_page_select]") . "
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["page"] * $_REQUEST["rows"]) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_view.php?page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $_REQUEST["rows"]) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = html_create_nav($_REQUEST["page"], MAX_DISPLAY_PAGES, $_REQUEST["rows"], $total_rows, 13, "mactrack_view_sites.php");
	}

	print $nav;

	$display_text = array(
		"nosort" => array("Actions", ""),
		"site_name" => array("Site Name", "ASC"),
		"ip_range" => array("IP Range", "ASC"),
		"ips_current" => array("Current IP Addresses", "DESC"),
		"ips_current_date" => array("Current Date", "DESC"),
		"ips_max" => array("Maximum IP Addresses", "DESC"),
		"ips_max_date" => array("Maximum Date", "DESC"));

	html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	$i = 0;
	if (sizeof($ip_ranges) > 0) {
		foreach ($ip_ranges as $ip_range) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
				?>
				<td width=60></td>
				<td width=200>
					<?php print "<p class='linkEditMain'><strong>" . $ip_range["site_name"] . "</strong></p>";?>
				</td>
				<td><?php print $ip_range["ip_range"];?></td>
				<td><?php print number_format($ip_range["ips_current"]);?></td>
				<td><?php print $ip_range["ips_current_date"];?></td>
				<td><?php print number_format($ip_range["ips_max"]);?></td>
				<td><?php print $ip_range["ips_max_date"];?></td>
			</tr>
			<?php
		}
	}else{
		print "<tr><td colspan='10'><em>No MacTrack Site IP Ranges Found</em></td></tr>";
	}

	print $nav;

	html_end_box(false);
}

?>