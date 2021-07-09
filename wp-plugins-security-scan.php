<?php
/*
Plugin Name: WP Plugins Security Scan
Plugin URI: https://github.com/mark2me/wp-plugins-security-scan
Description: 利用 WPScan 掃描你的外掛安全性。你必須先註冊 <a href="https://wpscan.com/api" target="_blank">WPSCAN API</a>。
Version: 1.0
Author: Simon Chuang
Author URI: https://wordpress.sig.tw/
License: GPLv2 or later
Text Domain: wp-plugins-security-scan
*/

defined( 'ABSPATH' ) || die();

define( 'WPSS_SLUG', 'wp-plugins-security-scan');
define( 'WPSS_VERSION', '1.0' );

require_once "setting.php";

new Wp_Plugins_Security_Scan();

class Wp_Plugins_Security_Scan {

    private $token;

    private $wpscan_api_url = 'https://wpscan.com/api/v3/plugins/';

    public function __construct(){
        $this->token = get_option( 'sig_wpscan_api', '' );
        add_action( 'plugins_loaded', array( $this, 'load_update_checker') );
        add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), array($this,'add_plugin_settings_link') );
        add_action( 'admin_menu', array( $this, 'add_admin_menu') );
    }


    public function add_plugin_settings_link($links) {
        array_unshift($links, '<a href="options-general.php">設定 Token</a>');
        array_unshift($links, '<a href="plugins.php?page='.WPSS_SLUG.'">比對外掛安全性</a>');
        return $links;
    }


    public function load_update_checker(){
        require 'plugin-update-checker/plugin-update-checker.php';
        $myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
            'https://github.com/mark2me/'.WPSS_SLUG.'/',
            __FILE__,
            WPSS_SLUG
        );

    }


    public function add_admin_menu(){
        add_submenu_page(
            'plugins.php',
            '比對外掛安全性',
            '比對外掛安全性',
            'manage_options',
            WPSS_SLUG,
            array( $this, 'plugins_check_page' )
        );
    }


    public function plugins_check_page() {

        if ( ! function_exists( 'get_plugins' ) ) {
	        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( ! function_exists( 'plugins_api' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }

        $active_plugins=get_option('active_plugins');
        $all_my_plugins = get_plugins();


        $objs = get_site_transient('update_plugins');

        if( !empty(get_option('timezone_string')) ){
            date_default_timezone_set(get_option('timezone_string'));
        }



    ?>
        <div class="wrap">
            <h2>外掛已知漏洞明細</h2>
            <br>

            <table class="wp-list-table widefat plugins">
                <thead>
                    <tr>
                        <th width="50">No.</th>
                        <th width="200">外掛名稱</th>
                        <th width="400">外掛說明</th>
                        <th width="250">外掛資訊</th>
                        <th width="400">最新已知漏洞</th>
                        <th width="150">下載</th>
                    </tr>
                </thead>
                <tbody>
            <?php
            if(count($all_my_plugins)>0){
                $i = 0;
                foreach($all_my_plugins as $index=>$plug){

                    if ( $plug['TextDomain'] == WPSS_SLUG ) continue;

                    //active
                    $active_status = (in_array($index, $active_plugins));  //啟用 or 停用

                    //
                    if( isset($objs->response[$index]) ){
                        $update = true;
                        $item = $objs->response[$index];
                    }else if( isset($objs->no_update[$index]) ){
                        $update = false;
                        $item = $objs->no_update[$index];
                    }else{
                        continue;
                    }

                    //
                    $pinfo = $this->get_plugin_info($item->slug);

            ?>
                <tr class="<?php echo ($active_status) ? 'active':'inactive';?>" data-plugin="<?php echo $k?>" style="<?php
                    echo ( $update ) ? 'color:#cc0000;':'';
                ?>">
                    <th style="text-align: center;<?php if($active_status) echo 'border-left: 4px solid #00a0d2;'?>"><?php echo $i+=1; ?>.</th>
                    <td class="plugin-title column-primary">
                        <a href="<?php echo $item->url?>" target="_blank"><?php echo $plug['Name']?></a>
                        <?php
                            echo '<div>Ver. ' . $plug['Version'] . '</div>';
                            if( $update ) echo '<h4>(有新版本: ' . $item->new_version . ')</h4>';
                        ?>
                    </td>
                    <td class="column-description desc">
						<div class="plugin-description"><?php
    						echo (!empty($pinfo->short_description)) ? $pinfo->short_description : $plug['Description'];
    				    ?></div>
    				</td>
    				<td class="column-description desc">
                        <ul>
                			<li>加入日期：<?php echo $pinfo->added;?></li>
                			<li>最近更新：<?php echo date('Y-m-d', strtotime($pinfo->last_updated));?></li>
                			<li>安裝次數：<?php echo number_format($pinfo->active_installs).'+';?></li>
                			<li>滿意度：<?php echo $pinfo->rating.'%';?></li>
                			<li>WordPress版本需求：<?php echo $pinfo->requires;?></li>
                			<li>已測試WordPress版本：<?php echo $pinfo->tested;?></li>
                			<?php if(!empty($pinfo->requires_php)) echo '<li>PHP版本需求：'.$pinfo->requires_php.'</li>';?>
                		</ul>
    				</td>
    				<td class="column-description desc"><?php
            			$this->show_check_result($item->slug,$plug['Version']);
    				?></td>
    				<td class="column-description desc">
        				<select onchange="location=this.options[this.selectedIndex].value;">
                        <option>下載其他版本</option>
                        <?php
                            foreach($pinfo->versions as $k=>$v){
                                echo '<option value="'.$v.'">'.$k.'</option>';
                            }
                        ?>
                        </select>
                    </td>
                </tr>
            <?php
                }
            }
            ?>
                </tbody>
            </table>
        </div>
    <?php

    }

    /**
     *  get plugin information with WP plugins_api
     */
    private function get_plugin_info($slug){

        $key = 'wp-plugins-info__'.$slug;

        $pinfo = get_transient($key);

        if( $pinfo === false ){
            $pinfo = plugins_api( 'plugin_information', array(
                'slug' => $slug,
                'locale' => get_user_locale(),
                'fields' => array(
                    'short_description' => true,
                    'tags' => false,
                    'sections' => false,
                    'contributors' => false,
                    'donate_link' => false,
                    'banners' => false,
                    'screenshots' => false
                )
            ));

            set_transient( $key, $pinfo, 60*60*24 ); //1天暫存
        }

        return $pinfo;
    }

    /**
     *  Show new wpscan result
     */
    private function show_check_result($slug='', $version=''){

        $result = $this->call_wpscan_api($slug);

        if( is_array($result) && isset($result['error']) ){
			echo 'Error: '.$result['error'];
		}else{
			$array = json_decode($result,true);
			$plug_info = $array[$slug];

            if( count($plug_info['vulnerabilities']) > 0 ){

    			$i = end( $plug_info['vulnerabilities'] );

                echo "<strong>名稱：{$i['title']}</strong>";
                echo '<ol>';
                echo "<li>類型：{$i['vuln_type']}</li>";
                if( version_compare($version,$i['fixed_in'],'<') ){
                echo "<li>修補：{$i['fixed_in']} ...儘速更新</li>";
                }else{
                echo "<li>修補：{$i['fixed_in']}</li>";
                }
                echo '</ol>';

			} else {
    			echo '(沒有相關漏洞資訊)';
			}
		}
    }

    /**
     *  call WPScan API
     */
    private function call_wpscan_api($slug=''){

        if( empty($slug) ) return array('error'=>'unknow slug');

        if( !empty($this->token) ){

            $key = 'wp-plugins-check__'.$slug;

            $json = get_transient($key);

            if( $json === false ){

                $response = wp_remote_get( $this->wpscan_api_url.$slug , array(
                    'headers' => array(
                        'Authorization' => 'Token token='.$this->token
                    )
                ) );
                $code = wp_remote_retrieve_response_code( $response );
                $json = wp_remote_retrieve_body( $response );

                if($code==200){
                    set_transient( $key, $json, 60*60*24*2 ); //2天暫存
                }else{
                    return array('error'=> wp_remote_retrieve_response_message( $response ));
                }
            }

            return $json;

        }else{
            return array('error'=>'(未設定 token)');
        }

    }
}
