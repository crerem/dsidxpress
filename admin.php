<?php

add_action("admin_init", "dsSearchAgent_Admin::Initialize");
add_action("admin_menu", "dsSearchAgent_Admin::AddMenu");

// hook into Google XML Sitemaps plugin http://wordpress.org/extend/plugins/google-sitemap-generator/
add_action("sm_buildmap", "dsSearchAgent_Admin::GoogleXMLSitemaps");

class dsSearchAgent_Admin {
	static function AddMenu() {
		$options = get_option(DSIDXPRESS_OPTION_NAME);

		add_menu_page('dsIDXPress', 'dsIDXPress', "manage_options", "dsidxpress", "", DSIDXPRESS_PLUGIN_URL . 'assets/idxpress_LOGOicon.png'); //, [icon_url]);

		if(isset($options["PrivateApiKey"]))
			$optionsPage = add_submenu_page("dsidxpress", "dsIDXPress Options", "Options", "manage_options", "dsidxpress", "dsSearchAgent_Admin::EditOptions");

		$activationPage = add_submenu_page("dsidxpress", "dsIDXpress Activation", "Activation", "manage_options", isset($options["PrivateApiKey"]) ? "dsidxpress_acivate" : "dsidxpress", "dsSearchAgent_Admin::Activation");

		add_action("admin_print_scripts-{$optionsPage}", "dsSearchAgent_Admin::LoadHeader");
		add_action("admin_print_scripts-{$activationPage}", "dsSearchAgent_Admin::LoadHeader");

		add_filter("mce_external_plugins", "dsSearchAgent_Admin::AddTinyMcePlugin");
		add_filter("mce_buttons", "dsSearchAgent_Admin::RegisterTinyMceButton");
	}
	static function AddTinyMcePlugin($plugins) {
		$plugins["idxlisting"] = DSIDXPRESS_PLUGIN_URL . "tinymce/single_listing/editor_plugin.js";
		$plugins["idxlistings"] = DSIDXPRESS_PLUGIN_URL . "tinymce/multi_listings/editor_plugin.js";
		return $plugins;
	}
	static function RegisterTinyMceButton($buttons) {
		array_push($buttons, "separator", "idxlisting", "idxlistings");
		return $buttons;
	}
	static function Initialize() {
		register_setting("dsidxpress_activation", DSIDXPRESS_OPTION_NAME, "dsSearchAgent_Admin::SanitizeOptions");
		register_setting("dsidxpress_options", DSIDXPRESS_API_OPTIONS_NAME, "dsSearchAgent_Admin::SanitizeApiOptions");
		register_setting("dsidxpress_options", DSIDXPRESS_CUSTOM_OPTIONS_NAME);

		wp_enqueue_script('dsidxpress_admin_options', DSIDXPRESS_PLUGIN_URL . 'js/admin-options.js', array('jquery','jquery-ui-sortable'), DSIDXPRESS_PLUGIN_VERSION);
	}
	static function LoadHeader() {
		$pluginUrl = DSIDXPRESS_PLUGIN_URL;

		echo <<<HTML
			<link rel="stylesheet" href="{$pluginUrl}css/admin-options.css" type="text/css" />
HTML;
	}

	static function EditOptions() {
		$options = get_option(DSIDXPRESS_CUSTOM_OPTIONS_NAME);

		$apiHttpResponse = dsSearchAgent_ApiRequest::FetchData("AccountOptions", array(), false, 0);

		if ($apiHttpResponse["response"]["code"] == "404")
			return array();
		else if (!empty($apiHttpResponse["errors"]) || substr($apiHttpResponse["response"]["code"], 0, 1) == "5")
			wp_die("We're sorry, but we ran into a temporary problem while trying to load the account data. Please check back soon.", "Account data load error");
		else
			$account_options = json_decode($apiHttpResponse["body"]);

		$urlBase = get_bloginfo("url");
		if (substr($urlBase, strlen($urlBase), 1) != "/") $urlBase .= "/";
		$urlBase .= dsSearchAgent_Rewrite::GetUrlSlug();
?>
	<div class="wrap metabox-holder">
		<div class="icon32" id="icon-options-general"><br/></div>
		<h2>dsIDXpress Options</h2>
		<form method="post" action="options.php">
			<?php settings_fields("dsidxpress_options"); ?>
			<h4>Display Settings</h4>

			<table class="form-table">
				<tr>
					<th>
						<label for="dsidxpress-CustomTitleText">Custom Title Text:</label>
					</th>
					<td>
						<input type="text" id="dsidxpress-CustomTitleText" maxlength="49" name="<?php echo DSIDXPRESS_API_OPTIONS_NAME; ?>[CustomTitleText]" value="<?php echo $account_options->CustomTitleText; ?>" />
						<span class="description">use <code>%title%</code> to designate where you want the location title like: <code>Real estate in the %title%</code></span>
					</td>
				</tr>
			</table>

			<h4>XML Sitemaps Locations</h4>
			<?php if ( in_array('google-sitemap-generator/sitemap.php', get_settings('active_plugins'))) {?>
			<span class="description">Add the Locations (City, Community, Tract, or Zip) to your XML Sitemap by adding them via the dialogs below.</span>
			<div class="dsidxpress-SitemapLocations stuffbox">
				<script>dsIDXPressOptions.UrlBase = '<?php echo $urlBase; ?>'; dsIDXPressOptions.OptionPrefix = '<?php echo DSIDXPRESS_CUSTOM_OPTIONS_NAME; ?>';</script>
				<h3><span class="hndle">Locations for Sitemap</span></h3>
				<div class="inside">
					<ul id="dsidxpress-SitemapLocations">
					<?php
						if(isset($options["SitemapLocations"]) && is_array($options["SitemapLocations"])){
							$location_index = 0;
							
							usort($options["SitemapLocations"], "dsSearchAgent_Admin::CompareListObjects");
							
							foreach($options["SitemapLocations"] as $key => $value){
								$location_sanitized = urlencode(strtolower(str_replace(array("-", " "), array("_", "-"), $value["value"])));
								?>
								<li class="ui-state-default dsidxpress-SitemapLocation">
									<div class="arrow"><span class="dsidxpress-up_down"></span></div>
									<div class="value">
										<a href="<?php echo $urlBase . $value["type"] .'/'. $location_sanitized;?>" target="_blank"><?php echo $value["value"]; ?></a>
										<input type="hidden" name="<?php echo DSIDXPRESS_CUSTOM_OPTIONS_NAME ; ?>[SitemapLocations][<?php echo $location_index; ?>][value]" value="<?php echo $value["value"]; ?>" />
									</div>
									<div class="priority">
										Priority: <select name="<?php echo DSIDXPRESS_CUSTOM_OPTIONS_NAME ; ?>[SitemapLocations][<?php echo $location_index; ?>][priority]">
											<option value="0.0"<?php echo ($value["priority"] == "0.0" ? ' selected="selected"' : '') ?>>0.0</option>
											<option value="0.1"<?php echo ($value["priority"] == "0.1" ? ' selected="selected"' : '') ?>>0.1</option>
											<option value="0.2"<?php echo ($value["priority"] == "0.2" ? ' selected="selected"' : '') ?>>0.2</option>
											<option value="0.3"<?php echo ($value["priority"] == "0.3" ? ' selected="selected"' : '') ?>>0.3</option>
											<option value="0.4"<?php echo ($value["priority"] == "0.4" ? ' selected="selected"' : '') ?>>0.4</option>
											<option value="0.5"<?php echo ($value["priority"] == "0.5" || !isset($value["priority"]) ? ' selected="selected"' : '') ?>>0.5</option>
											<option value="0.6"<?php echo ($value["priority"] == "0.6" ? ' selected="selected"' : '') ?>>0.6</option>
											<option value="0.7"<?php echo ($value["priority"] == "0.7" ? ' selected="selected"' : '') ?>>0.7</option>
											<option value="0.8"<?php echo ($value["priority"] == "0.8" ? ' selected="selected"' : '') ?>>0.8</option>
											<option value="0.9"<?php echo ($value["priority"] == "0.9" ? ' selected="selected"' : '') ?>>0.9</option>
											<option value="1.0"<?php echo ($value["priority"] == "1.0" ? ' selected="selected"' : '') ?>>1.0</option>
										</select>
									</div>
									<div class="type"><select name="<?php echo DSIDXPRESS_CUSTOM_OPTIONS_NAME ; ?>[SitemapLocations][<?php echo $location_index; ?>][type]">
										<option value="city"<?php echo ($value["type"] == "city" ? ' selected="selected"' : ''); ?>>City</option>
										<option value="community"<?php echo ($value["type"] == "community" ? ' selected="selected"' : ''); ?>>Community</option>
										<option value="tract"<?php echo ($value["type"] == "tract" ? ' selected="selected"' : ''); ?>>Tract</option>
										<option value="zip"<?php echo ($value["type"] == "zip" ? ' selected="selected"' : ''); ?>>Zip Code</option>
									</select></div>
									<div class="action"><input type="button" value="Remove" class="button" onclick="dsIDXPressOptions.RemoveSitemapLocation(this)" /></div>
									<div style="clear:both"></div>
								</li>
								<?php
								$location_index++;
							}
						}
					?>
					</ul>

					<div class="dsidxpress-SitemapLocationsNew">
						<div class="arrow">New:</div>
						<div class="value"><input type="text" id="dsidxpress-NewSitemapLocation" maxlength="49" value="" /></div>
						<div class="type">
							<select class="widefat" id="dsidxpress-NewSitemapLocationType"">
								<option value="city">City</option>
								<option value="community">Community</option>
								<option value="tract">Tract</option>
								<option value="zip">Zip Code</option>
							</select>
						</div>
						<div class="action">
							<input type="button" class="button" id="dsidxpress-NewSitemapLocationAdd" value="Add" onclick="dsIDXPressOptions.AddSitemapLocation()" />
						</div>
						<div style="clear:both"></div>
					</div>	
				</div>
			</div>
			
			<span class="description">"Priority" gives a hint to the web crawler as to what you think the importance of each page is. <code>1</code> being highest and <code>0</code> lowest.</span>

			<h4>XML Sitemaps Options</h4>
			<table class="form-table">
				<tr>
					<th>
						<label for="<?php echo DSIDXPRESS_CUSTOM_OPTIONS_NAME ; ?>[SitemapFrequency]">Frequency:</label>
					</th>
					<td>
						<select name="<?php echo DSIDXPRESS_CUSTOM_OPTIONS_NAME ; ?>[SitemapFrequency]" id="<?php echo DSIDXPRESS_CUSTOM_OPTIONS_NAME ; ?>_SitemapFrequency">
							<!--<option value="always"<?php echo ($options["SitemapFrequency"] == "always" ? ' selected="selected"' : '') ?>>Always</option> -->
							<option value="hourly"<?php echo ($options["SitemapFrequency"] == "hourly" ? 'selected="selected"' : '') ?>>Hourly</option>
							<option value="daily"<?php echo ($options["SitemapFrequency"] == "daily" || !isset($options["SitemapFrequency"]) ? 'selected="selected"' : '') ?>>Daily</option>
							<!--<option value="weekly"<?php echo ($options["SitemapFrequency"] == "weekly" ? 'selected="selected"' : '') ?>>Weekly</option>
							<option value="monthly"<?php echo ($options["SitemapFrequency"] == "monthly" ? 'selected="selected"' : '') ?>>Monthly</option>
							<option value="yearly"<?php echo ($options["SitemapFrequency"] == "yearly" ? 'selected="selected"' : '') ?>>Yearly</option>
							<option value="never"<?php echo ($options["SitemapFrequency"] == "never" ? 'selected="selected"' : '') ?>>Never</option> -->
						</select>
						<span class="description">The "hint" to send to the crawler. This does not guarantee frequency, crawler will do what they want.</span>
					</td>
				</tr>
			</table>
			<?php } else { ?>
				<span class="description">To enable this functionality, install and activate this plugin: <a href="http://wordpress.org/extend/plugins/google-sitemap-generator/" target="_blank">Google XML Sitemaps</a></span>
			<?php }?>
			<p class="submit">
				<input type="submit" class="button-primary" name="Submit" value="Save Options" />
			</p>
		</form>
	</div><?php
	}

	static function Activation() {
		$options = get_option(DSIDXPRESS_OPTION_NAME);

		if ($options["PrivateApiKey"]) {
			$diagnostics = self::RunDiagnostics($options);
			$formattedApiKey = $options["AccountID"] . "/" . $options["SearchSetupID"] . "/" . $options["PrivateApiKey"];
		}
?>
	<div class="wrap metabox-holder">
		<div class="icon32" id="icon-options-general"><br/></div>
		<h2>dsIDXpress Activation</h2>
		<form method="post" action="options.php">
			<?php settings_fields("dsidxpress_activation"); ?>

			<h3>Plugin activation</h3>
			<p>
				In order to use <i>dsIDXpress</i>
				to display real estate listings from the MLS on your blog, you must have an activation key from
				<a href="http://www.diversesolutions.com/" target="_blank">Diverse Solutions</a>. Without it, the plugin itself
				will be useless, widgets won't appear, and all "shortcodes" specific to this plugin in your post and page
				content will be hidden when that content is displayed on your blog. If you already have this activation key, enter it
				below and you can be on your way.
			</p>
			<p>
				If you <b>don't</b> yet have an activation key, you can purchase one from us
				(<a href="http://www.diversesolutions.com/" target="_blank">Diverse Solutions</a>) for a monthly price that
				varies depending on the MLS you belong to. Furthermore, in order for us to authorize the data to be transferred
				from us to your blog, you <b>must</b> be a member of the MLS you would like the data for. In some cases, you
				even have to be a real estate broker (or have your broker sign off on your request for this data). If you're 1)
				a real estate agent, and 2) a member of an MLS, and you're interested in finding out more, please
				<a href="http://www.diversesolutions.com/" target="_blank">contact us</a>.
			</p>
			<table class="form-table">
				<tr>
					<th style="width: 110px;">
						<label for="option-FullApiKey">Activation key:</label>
					</th>
					<td>
						<input type="text" id="option-FullApiKey" maxlength="49" name="<?php echo DSIDXPRESS_OPTION_NAME; ?>[FullApiKey]" value="<?php echo $formattedApiKey ?>" />
					</td>
				</tr>
				<tr>
					<th style="width: 110px;">Current status:</th>
					<td class="dsidx-status dsidx-<?php echo $diagnostics["DiagnosticsSuccessful"] ? "success" : "failure" ?>">
						** <?php echo $diagnostics && $diagnostics["DiagnosticsSuccessful"] ? "ACTIVE" : "INACTIVE" ?> **
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" class="button-primary" name="Submit" value="Activate Plugin For This Blog / Server" />
			</p>

<?php
		if ($diagnostics) {
?>
			<h3>Diagnostics</h3>
<?php
			if ($diagnostics["error"]) {
?>
			<p class="error">
				It seems that there was an issue while trying to load the diagnostics from Diverse Solutions' servers. It's possible that our servers
				are temporarily down, so please check back in just a minute. If this problem persists, please
				<a href="http://www.diversesolutions.com/support.htm" target="_blank">contact us</a>.
			</p>
<?php
			} else {
?>
			<table class="form-table" style="margin-bottom: 15px;">
				<tr>
					<th style="width: 230px;">Account active?</th>
					<td class="dsidx-status dsidx-<?php echo $diagnostics["IsAccountValid"] ? "success" : "failure" ?>">
						<?php echo $diagnostics["IsAccountValid"] ? "Yes" : "No" ?>
					</td>

					<th style="width: 290px;">Activation key active?</th>
					<td class="dsidx-status dsidx-<?php echo $diagnostics["IsApiKeyValid"] ? "success" : "failure" ?>">
						<?php echo $diagnostics["IsApiKeyValid"] ? "Yes" : "No" ?>
					</td>
				</tr>
				<tr>
					<th>Account authorized for this MLS?</th>
					<td class="dsidx-status dsidx-<?php echo $diagnostics["IsAccountAuthorizedToMLS"] ? "success" : "failure" ?>">
						<?php echo $diagnostics["IsAccountAuthorizedToMLS"] ? "Yes" : "No" ?>
					</td>

					<th>Activation key authorized for this blog?</th>
					<td class="dsidx-status dsidx-<?php echo $diagnostics["IsApiKeyAuthorizedToUri"] ? "success" : "failure" ?>">
						<?php echo $diagnostics["IsApiKeyAuthorizedToUri"] ? "Yes" : "No" ?>
					</td>
				</tr>
				<tr>
					<th>Clock accurate on this server?</th>
					<td class="dsidx-status dsidx-<?php echo $diagnostics["ClockIsAccurate"] ? "success" : "failure" ?>">
						<?php echo $diagnostics["ClockIsAccurate"] ? "Yes" : "No" ?>
					</td>

					<th>Activation key authorized for this server?</th>
					<td class="dsidx-status dsidx-<?php echo $diagnostics["IsApiKeyAuthorizedToIP"] ? "success" : "failure" ?>">
						<?php echo $diagnostics["IsApiKeyAuthorizedToIP"] ? "Yes" : "No" ?>
					</td>
				</tr>
				<tr>
					<th>WordPress link structure ok?</th>
					<td class="dsidx-status dsidx-<?php echo $diagnostics["UrlInterceptSet"] ? "success" : "failure" ?>">
						<?php echo $diagnostics["UrlInterceptSet"] ? "Yes" : "No" ?>
					</td>

					<th>Under monthly API call limit?</th>
					<td class="dsidx-status dsidx-<?php echo $diagnostics["UnderMonthlyCallLimit"] ? "success" : "failure" ?>">
						<?php echo $diagnostics["UnderMonthlyCallLimit"] ? "Yes" : "No" ?>
					</td>
				</tr>
				<tr>
					<th>Server PHP version at least 5.2?</th>
					<td class="dsidx-status dsidx-<?php echo $diagnostics["PhpVersionAcceptable"] ? "success" : "failure" ?>">
						<?php echo $diagnostics["PhpVersionAcceptable"] ? "Yes" : "No" ?>
					</td>

					<th>Would you like fries with that?</th>
					<td class="dsidx-status dsidx-success">
						Yes <!-- you kidding? we ALWAYS want fries. mmmm, friessssss -->
					</td>
				</tr>
			</table>
<?php
			}
		}
?>
		</form>
	</div>
<?php
	}
	static function RunDiagnostics($options) {
		// it's possible for a malicious script to trick a blog owner's browser into running the Diagnostics which passes the PrivateApiKey which
		// could allow a bug on the wire to pick up the key, but 1) we have IP and URL restrictions, and 2) there are much bigger issues than the
		// key going over the wire in the clear if the traffic is being spied on in the first place
		global $wp_rewrite;

		$diagnostics = dsSearchAgent_ApiRequest::FetchData("Diagnostics", array("apiKey" => $options["PrivateApiKey"]), false, 0);
		if (empty($diagnostics["body"]) || $diagnostics["response"]["code"] != "200")
			return array("error" => true);

		$diagnostics = (array)json_decode($diagnostics["body"]);
		$setDiagnostics = array();
		$timeDiff = time() - strtotime($diagnostics["CurrentServerTimeUtc"]);
		$secondsIn2Hrs = 60 * 60 * 2;

		$setDiagnostics["IsApiKeyValid"] = $diagnostics["IsApiKeyValid"];
		$setDiagnostics["IsAccountAuthorizedToMLS"] = $diagnostics["IsAccountAuthorizedToMLS"];
		$setDiagnostics["IsAccountValid"] = $diagnostics["IsAccountValid"];
		$setDiagnostics["IsApiKeyAuthorizedToUri"] = $diagnostics["IsApiKeyAuthorizedToUri"];
		$setDiagnostics["IsApiKeyAuthorizedToIP"] = $diagnostics["IsApiKeyAuthorizedToIP"];

		$setDiagnostics["PhpVersionAcceptable"] = version_compare(phpversion(), DSIDXPRESS_MIN_VERSION_PHP) != -1;
		$setDiagnostics["UrlInterceptSet"] = get_option("permalink_structure") != "";
		$setDiagnostics["ClockIsAccurate"] = $timeDiff < $secondsIn2Hrs && $timeDiff > -1 * $secondsIn2Hrs;
		$setDiagnostics["UnderMonthlyCallLimit"] = $diagnostics["AllowedApiRequestCount"] === 0 || $diagnostics["AllowedApiRequestCount"] > $diagnostics["CurrentApiRequestCount"];

		$setDiagnostics["DiagnosticsSuccessful"] = true;
		foreach ($setDiagnostics as $key => $value) {
			if (!$value)
				$setDiagnostics["DiagnosticsSuccessful"] = false;
		}

		$options["Activated"] = $setDiagnostics["DiagnosticsSuccessful"];
		update_option(DSIDXPRESS_OPTION_NAME, $options);
		$wp_rewrite->flush_rules();

		return $setDiagnostics;
	}
	static function SanitizeOptions($options) {
		if ($options["FullApiKey"]) {
			$apiKeyParts = explode("/", $options["FullApiKey"]);

			$options["AccountID"] = $apiKeyParts[0];
			$options["SearchSetupID"] = $apiKeyParts[1];
			$options["PrivateApiKey"] = $apiKeyParts[2];

			dsSearchAgent_ApiRequest::FetchData("BindToRequester", array(), false, 0, $options);

			unset($options["FullApiKey"]);
		}
		return $options;
	}

	/*
	 * We're using the sanitize to capture the POST for these options so we can send them back to the diverse API
	 * since we save and consume -most- options there.
	 */
	static function SanitizeApiOptions($options){
		if(isset($options) && is_array($options)){
			$options_text = "";

			foreach($options as $key => $value){
				if($options_text != "") $options_text .= ",";
				$options_text .= $key.'|'.urlencode($value);
				unset($options[$key]);
			}

			$result = dsSearchAgent_ApiRequest::FetchData("SaveAccountOptions", array("options" => $options_text), false, 0);
		}
		return $options;
	}

	static function GoogleXMLSitemaps() {
		$options = get_option(DSIDXPRESS_CUSTOM_OPTIONS_NAME);

		$urlBase = get_bloginfo("url");
		if (substr($urlBase, strlen($urlBase), 1) != "/") $urlBase .= "/";
		$urlBase .= dsSearchAgent_Rewrite::GetUrlSlug();

		if (in_array('google-sitemap-generator/sitemap.php', get_settings('active_plugins'))) {
			$generatorObject = &GoogleSitemapGenerator::GetInstance();

			if($generatorObject != null && isset($options["SitemapLocations"]) && is_array($options["SitemapLocations"])){
				$location_index = 0;

				usort($options["SitemapLocations"], "dsSearchAgent_Admin::CompareListObjects");
							
				foreach($options["SitemapLocations"] as $key => $value){
					$location_sanitized = urlencode(strtolower(str_replace(array("-", " "), array("_", "-"), $value["value"])));
					$url = $urlBase . $value["type"] .'/'. $location_sanitized;

					$generatorObject->AddUrl($url, time(), $options["SitemapFrequency"], floatval($options["SitemapPriority"]));
				}
			}
   		}
	}
	
	static function CompareListObjects($a, $b)
    {
        $al = strtolower($a["value"]);
        $bl = strtolower($b["value"]);
        if ($al == $bl) {
            return 0;
        }
        return ($al > $bl) ? +1 : -1;
    }
}
?>