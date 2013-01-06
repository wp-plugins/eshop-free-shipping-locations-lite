<?php defined('ABSPATH') or die("No direct access allowed");
/*
* Plugin Name:   eShop Free Shipping Locations Lite
* Plugin URI:	 http://usestrict.net/2013/01/eshop-free-shipping-locations-lite
* Description:   Allow free shipping depending on the user's country
* Version:       1.0
* Author:        Vinny Alves
* Author URI:    http://www.usestrict.net
*
* License:       GNU General Public License, v2 (or newer)
* License URI:  http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* Copyright (C) 2013 www.usestrict.net, released under the GNU General Public License.
*/
class USC_eShop_Free_Shipping_Locations_Lite
{
	public $domain  = 'eshop-free-shipping-locations-lite';
	public $version = '1.0';
	public $saved   = FALSE;
	public $my_url  = '';
	
	function __construct()
	{
		$this->my_url = plugins_url($this->domain);
		
		if (is_admin() && $_GET['mstatus'] && $_GET['mstatus'] == 'Discounts')
		{
			add_action('admin_init', array(&$this,'_add_meta_box'));
		}
		
		if (is_admin())
		{
			add_filter('pre_update_option_eshop_plugin_settings', array(&$this,'_save_option'),10,1);
			add_action('wp_ajax_' . $this->domain . '-get-states', array(&$this,'_fetch_states'));
		}
		
		add_filter('eshop_is_shipfree',array(&$this,'is_ship_free'),10,2);
	}
	
	/**
	 * @method is_ship_free
	 * @desc Our implementation of is_shipfree, accessed by a filter
	 * @param bool $shipfree
	 * @param float $total
	 */
	public function is_ship_free($shipfree,$total)
	{
		$opt = get_option($this->domain);
		
		if ($opt)
		{
			if ($opt['usc_free_shipping_lite_country'] === 'off')
			{
				return $shipfree;
			}
			elseif ($_REQUEST)
			{
				if ($_REQUEST['country']) $country = $_REQUEST['country'];
				
				if     ($_REQUEST['altstate'])  $state = $_REQUEST['altstate'];
				elseif ($_REQUEST['state'])     $state = $_REQUEST['state'];
				
				if (! is_numeric($state))
				{
					$state = $this->_get_state_id($country,$state);
				}
				
				if ($country === $opt['usc_free_shipping_lite_country'] && 
					$state   === $opt['usc_free_shipping_lite_state']) 
				{
					return TRUE;
				}
			}
		}
		
		return FALSE;
	}
	
	/**
	 * @method _get_state_id
	 * @desc Fetches the ID if a state if code/name given
	 */
	public function _get_state_id($country,$state)
	{
		global $wpdb;
		
		if (! $country) return $state;
		
		$sql = "select id from " . $wpdb->prefix . 'eshop_states where list = %s and (code = %s or stateName = %s)';
		$query = $wpdb->prepare($sql,$country,$state,$state);
		return $wpdb->get_var($query);
	}
	
	
	/**
	 * @method _fetch_states
	 * @desc Gets states from the Ajax call
	 */
	public function _fetch_states($country=NULL, $wantreturn=FALSE)
	{
		global $wpdb;
		
		if (! $country && $_REQUEST['country']) $country = $_REQUEST['country'];
		
		if (! $country || $country == 'off')
		{
			return FALSE;
		}
		
		$sql = "select id, stateName from " . $wpdb->prefix . "eshop_states where list = %s";
		$query = $wpdb->prepare($sql,$country);
		
		$rows = $wpdb->get_results($query,ARRAY_A);
		
		if ($wantreturn)
		{
			return $rows;
		}
		
		$out['success'] = false;
		if (count($rows))
		{
			$out['success'] = true;
			$out['data'] = $rows;
		}
		else 
		{
			$out['msgs'][] = '<span class="error">No states found!</span><br />You should manually add some states using eShop->Shipping UI, or check out the '.
							'<a href="http://usestrict.net/2012/09/eshop-dynamic-checkout-statecountyprovince-region-packs/" ' . 
							'target="_new">eShop Checkout Dynamic States</a> suite.';
		}
		
		echo json_encode($out);
		exit;
	}

	/**
	 * @method _save_option
	 * @desc Saves the Location option
	 * @param array $params - the eShop Options params. we're only hitching a ride here. The $params are just a passthru.
	 */
	public function _save_option($params)
	{
		if (! $_POST['usc_free_shipping_lite_country']) return $params;
		
		$fields = array('usc_free_shipping_lite_country' => $_POST['usc_free_shipping_lite_country'],
						'usc_free_shipping_lite_state'   => $_POST['usc_free_shipping_lite_state']);
		
		if (current_user_can('eShop_admin') &&
			$this->saved === FALSE)
		{
			$clean = $this->_sanitize_input($fields);
			
			update_option($this->domain,$clean);
			$this->saved = TRUE;
		}
		
		return $params;
	}
	
	/**
	 * @method _sanitize_input
	 * @desc Sanitizes form data
	 */
	public function _sanitize_input($params)
	{
		if (! $params['usc_free_shipping_lite_country'])
		{
			$params['usc_free_shipping_lite_country'] = 'off';
		}
		
		if ($params['usc_free_shipping_lite_country'] === 'off')
			unset($params['usc_free_shipping_lite_state']);
		
		return $params;
	}
	
	
	/**
	 * @method _add_meta_box
	 * @desc Calls the add_meta_box hook
	 */
	public function _add_meta_box()
	{
		global $eshop_metabox_plugin;
		
		if (!current_user_can('eShop_admin')) return;

		add_meta_box('usc-eshop-free-shipping-locations-lite', __('Free Shipping Locations Lite',$this->domain), 
					 array(&$this, '_render_meta_box_content'), $eshop_metabox_plugin->pagehook, 'normal', 'default');
	}
	
	/**
	 * @method _render_meta_box_content
	 * @desc Displays the actual meta-box content
	 * @param array $eshop_opts
	 */
	public function _render_meta_box_content($eshop_opts)
	{
		global $wpdb;
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($){
				$("a.usc_free_shipping").click(function(e){
					if ($(this).attr('href') == '#') {
						e.preventDefault();
					}
				});

				$("#usc_go_pro_link").click(function(){
					$("div#usc_why_go_pro").toggle();
				});

				$("#usc_free_shipping_lite_country").change(function(){

					$("#usc_free_shipping_lite_error").hide();
					
					if ($(this).val() == 'off') {
						$("#usc_free_shipping_lite_state_div").hide();
					}
					else {

						$("#usc_free_shipping_location_throbber").show();
						$.ajax({
							type    : 'GET',
							url     : '<?php echo admin_url( 'admin-ajax.php' ); ?>',
							data    : {
										action : '<?php echo $this->domain; ?>-get-states',
										country : $("#usc_free_shipping_lite_country").val()
									  },
							dataType: 'json',			
							success : function(ajax_response){
								$("#usc_free_shipping_location_throbber").hide();

								if (! ajax_response.success) {

									$("#usc_free_shipping_lite_state").find('option').remove().end()
									.append($('<option/>',{value : '', disabled: 'disabled'}).text('No states found!'));
									
									$("#usc_free_shipping_lite_state_div").hide();
									$("#usc_free_shipping_lite_error").html(ajax_response.msgs.join("<br />")).show();
									return;
								}

								$("#usc_free_shipping_lite_state").find('option').remove();
								$.each(ajax_response.data, function(undef,obj){
									$("#usc_free_shipping_lite_state").append($('<option/>',{value : obj.code}).text(obj.stateName));
								});

								$("#usc_free_shipping_lite_error").hide();
								$("#usc_free_shipping_lite_state_div").show();
							}
						});
					}
				});
			});
		</script>
		
		<style type="text/css">
			hr.usc {border:0; border-top: 1px solid #ccc}
			#usc_why_go_pro {display:none}
			#usc_free_shipping_location_throbber {display:none}
			#usc_free_shipping_lite_error {display:none}
			.error {color: red; font-weight:bold;} 
		</style>
		
		<?php echo class_exists('USC_eShop_Free_Shipping_Locations')?>
		<p>Choose a Location below to which shipping will be free.</p>
		<p><a class="usc_free_shipping" href="#" id="usc_go_pro_link">Why should you consider getting the Pro version?</a></p>
		
		<div id="usc_why_go_pro">
			<p>Things you can do using the Pro version:</p>
			<ol>
				<li>Choose multiple Countries/States to offer free shipping (the Lite version only sets free shipping to a single state).</li>
				<li>Play nicely with values specified in the "Spend over to get free shipping" field. The "lite" version forces free shipping
				    to the selected location, regardless of any values in the "Spend Over" field.</li>
				<li>Choose whether to use the client or shipping address to determine free shipping (the Lite version uses the Client address only).</li>
			</ol>
		</div>
		<hr class="usc" />
		<?php 
			$sql = "select code, country from " . $wpdb->prefix . 'eshop_countries order by country';
			$country_rows = $wpdb->get_results($sql);
			$opts = get_option($this->domain);
			
			if (is_array($opts)) 
			{
				$country_sel = $opts['usc_free_shipping_lite_country'];
				$state_sel   = $opts['usc_free_shipping_lite_state'];
			}
			
			$state_rows = $this->_fetch_states($country_sel,TRUE);
		?>
		
		Choose a Country: 
		<select id="usc_free_shipping_lite_country" name="usc_free_shipping_lite_country">
			<option value="off" <?php echo ($country_sel == 'off') ? 'selected="selected"' : '';?>>OFF</option>
			<option value="" disabled="disabled">----------------------</option>
		<?php foreach ($country_rows as $row) :?>
			<option value="<?php echo $row->code; ?>"  <?php echo ($country_sel == $row->code) ? 'selected="selected"' : '';?>><?php echo $row->country; ?></option>
		<?php endforeach ?>
		</select> <img src="<?php echo $this->my_url; ?>/throbber.gif" id="usc_free_shipping_location_throbber" />
		
		
		<div id="usc_free_shipping_lite_error"></div>
		<div id="usc_free_shipping_lite_state_div" <?php echo ($country_sel == 'off') ? 'style="display:none"': '';?>>
		Free shipping to:	
			<select id="usc_free_shipping_lite_state" name="usc_free_shipping_lite_state">
			<?php 
				if ($country_sel)
				{
					if (is_array($state_rows) && count($state_rows))
					{
						foreach ($state_rows as $row) : ?>
				<option value="<?php echo $row['id']; ?>" <?php echo $row['id'] == $state_sel ? 'selected="selected"' : ''?>
				 ><?php echo $row['stateName']; ?></option>
						<?php endforeach;
					}
					else 
					{ ?>
				<option value="" disabled="disabled" selected="selected">No States Found!</option>
					<?php 
					}
				}
			?>			
			</select>
		</div>
		<?php 
	}
}

$USC_eShop_Free_Shipping_Locations_Lite = new USC_eShop_Free_Shipping_Locations_Lite();

/* End of file eshop-free-shipping-locations-lite.php */
/* Location: eshop-free-shipping-locations-lite/eshop-free-shipping-locations-lite.php */