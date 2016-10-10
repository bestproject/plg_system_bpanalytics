<?php
/**
 * @version		1.0.0
 * @author		Artur Stępień
 * @copyright	Copyright (C) 2016 Best Project, Inc. All rights reserved.
 * @license		http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 */
defined('_JEXEC') or die;
jimport('joomla.language.helper');
jimport('joomla.plugin.plugin');

/**
 * Google Analytics integration Plugin
 */
class plgSystemBPAnalytics extends JPlugin
{

	/**
	 * Prepare content
	 */
	function onAfterRender()
	{
		$app = JFactory::getApplication();

		// Exit if executed on Administration
		if ($app->isAdmin()) {
			return;
		}

		//Prepare plugin settings
		$settings				 = array(
			'domain' => $this->params->get('domain', ''),
			'identities' => json_decode($this->params->get('identities','{\'identities\':[]}')),
			'betterlinks' => (bool) (int) $this->params->get('betterlinks', '0'),
			'userid' => (bool) (int) $this->params->get('userid', '0'),
			'trackhash' => (bool) (int) $this->params->get('trackhash', '0'),
			'trackbuttonclicks' => (bool) (int) $this->params->get('trackbuttonclicks','0'),
		);
		$settings['identities']	 = $settings['identities']->identity;

		// Exit if non correct identity given
		$identity_found = false;
		foreach ($settings['identities'] AS $identity) {
			if ($identity !== '') {
				$identity_found = true;
				break;
			}
		}
		if (!$identity_found) {
			return;
		}

		// Process domain
		if (empty($settings['domain'])) {
			$settings['domain']	 = $_SERVER['HTTP_HOST'];
		}
		if (substr($settings['domain'], 0, 1) !== '.') {
			$settings['domain']	 = '.'.$settings['domain'];
		}

		// Insert tracking code
		$code = $this->getTrackingCode($settings);
		$app->setBody(str_ireplace(
				'</head>', $code.'</head>', $app->getBody()
		));
	}

	/**
	 * Returns Google Analytics integration code
	 * 
	 * @param   array    $settings Settings of trakcer code.
	 * 
	 * @return   string
	 */
	function getTrackingCode($settings)
	{

		// Basick trackers settings
		$options = array(
			'cookieDomain'=>$settings['domain'],
		);

		// Track user activity
		$user = JFactory::getUser();
		if( $settings['userid'] AND !$user->guest ) {
			$options['userid'] = $user->username;
		}

		$code = "<script>(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');\n";
		$code.= "\n// Trackers definitions\n";
		$code_pageview = '';
		foreach( $settings['identities'] AS $id=>$identity ) {
			$options['name'] = 'bpanalytics_'.$id;
			$code.= "ga('create', '$identity', 'auto', ".json_encode($options).");\n";
			
			// Include Display Features plugin if enabled
			if( $this->params->get('plugin_display_features',0) ) {
				$code.= "ga('{$options['name']}.require', 'displayfeatures');\n";
			}

			// Include Linker plugin if enabled
			if( $this->params->get('plugin_linker',0) ) {
				$code.= "ga('{$options['name']}.require', 'linker');\n";
			}

			// Include  Enhanced Link Attribution  plugin if enabled
			if( $this->params->get('plugin_enhanced_links',0) ) {
				$code.= "ga('{$options['name']}.require', 'linkid');\n";
			}

			// Add pageview for every tracker
			$code_pageview.= "ga('bpanalytics_{$id}.send', 'pageview');\n";
		}

		// Include custom code if exists
		$custom_code = $this->params->get('custom_code','');
		if( !empty($custom_code) ) {
			$code.= "\n// Custom code\n";
			$code.= "{$this->params->get('custom_code','')}\n";
		}

		$code.= "\n// Sending pageviews\n";
		$code.=  $code_pageview;
		
		// Include hash URLs tracking (eg. #contact)
		$trackhash = $this->params->get('trackhash',0);
		if( !empty($trackhash) ) {
			JHTML::_('jquery.framework');
			$code.= "\n\n// Single Page Application support\n";
			$code.= "jQuery(document).ready(function($){\n";
			$code.= "\t$('a[href^=\"#\"]').click(function(){\n";
				
			$code.= "\t\tconsole.log('Sending page view for: '+$(this).attr('href'));\n";
			foreach( $settings['identities'] AS $id=>$identity ) {
				$code.= "\t\tga('{$options['name']}.send', {hitType:'pageview',title:$(this).attr('href'),page:$(this).attr('href')});\n";
			}

			$code.="\t});\n";
			$code.="});\n";
		}

		// Include buttons click tracking
		$trackbuttonclicks = $this->params->get('trackbuttonclicks',0);
		if( !empty($trackbuttonclicks) ) {
			JHTML::_('jquery.framework');
			$code.= "\n\n// Buttons click tracking\n";
			$code.= "jQuery(document).ready(function($){\n";
			$code.= "\t$('button,a.btn,input[type=\"button\"],input[type=\"submit\"]').click(function(){\n";

			$code.= "\t\tconsole.log('Sending button click event for: '+$(this).text());\n";
			foreach( $settings['identities'] AS $id=>$identity ) {
				$code.= "\t\tga('{$options['name']}.send', {hitType:'event', eventCategory:'Buttons clicks', eventAction:$(this).text(), eventLabel:document.title});\n";
			}

			$code.="\t});\n";
			$code.="});\n";
		}

		$code.= "</script>";

		return $code;
	}
}