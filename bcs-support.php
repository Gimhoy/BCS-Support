<?php
/**
 * @package BCS Support
 * @version 1.1.1
 */
/*
Plugin Name: BCS Support
Plugin URI: http://blog.gimhoy.com/archives/bcs-support.html
Description: This is a plugin for bcs.
Author: HJin.me & Gimhoy
Version: 1.1.1
Author URI: http://blog.gimhoy.com
*/

if ( !defined('WP_PLUGIN_URL') )
	define( 'WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins' );                           //  plugin url

define('BCS_BASENAME', plugin_basename(__FILE__));
define('BCS_BASEFOLDER', plugin_basename(dirname(__FILE__)));
define('BCS_FILENAME', str_replace(DFM_BASEFOLDER.'/', '', plugin_basename(__FILE__)));

// 初始化选项
register_activation_hook(__FILE__, 'bcs_set_options');

/**
 * 初始化选项
 */
function bcs_set_options() {
    $options = array(
        'bucket' => "",
        'ak' => "",
    	'sk' => "",
    );
    
    add_option('bcs_options', $options, '', 'yes');
}


function bcs_admin_warnings() {
    $bcs_options = get_option('bcs_options', TRUE);

    $bcs_bucket = attribute_escape($bcs_options['bucket']);
	if ( !$bcs_options['bucket'] && !isset($_POST['submit']) ) {
		function bcs_warning() {
			echo "
			<div id='bcs-warning' class='updated fade'><p><strong>".__('Bcs is almost ready.')."</strong> ".sprintf(__('You must <a href="%1$s">enter your BCS Bucket </a> for it to work.'), "options-general.php?page=" . BCS_BASEFOLDER . "/bcs-support.php")."</p></div>
			";
		}
		add_action('admin_notices', 'bcs_warning');
		return;
	} 
}
bcs_admin_warnings();
/*
 Hook 所有上传操作，上传完成后再存到云存储。
 默认设置为 public
*/
function mv_attachments_to_bcs($data) {
	
	require_once('bcs.class.php');
	$bcs_options = get_option('bcs_options', TRUE);
    $bcs_bucket = attribute_escape($bcs_options['bucket']);
    if(false === getenv ( 'HTTP_BAE_ENV_AK' )) {
	    $bcs_ak = attribute_escape($bcs_options['ak']);
    }
    if(false === getenv ( 'HTTP_BAE_ENV_SK' )) {
	    $bcs_sk = attribute_escape($bcs_options['sk']);
    }
    
	$baidu_bcs = new BaiduBCS($bcs_ak, $bcs_sk);


	$bucket = $bcs_bucket;
	$year = date("Y");
	$month = date("m");
	$object =  "/blog/".$year.$month."/".basename($data['file']);
	$file = $data['file'];
	$refererurl = preg_replace('#^www\.#', '', strtolower($_SERVER['SERVER_NAME']));
	$acl = array (
			'statements' => array (
					'0' => array (
							'user' => array (
									"*" ), 
							'resource' => array (
									$bucket . '/' .$object), 
							'action' => array (
									BaiduBCS::BCS_SDK_ACL_ACTION_GET_OBJECT
									 ), 
							'effect' => BaiduBCS::BCS_SDK_ACL_EFFECT_ALLOW,
							'referer' => array (
									$refererurl ) ) ) );
	$opt = array($acl);
	$baidu_bcs->create_object ( $bucket, $object, $file, $opt );

	$url = "http://bcs.duapp.com/{$bucket}{$object}"; 
	
	return array( 'file' => $url, 'url' => $url, 'type' => $data['type'] );
}

add_filter('wp_handle_upload', 'mv_attachments_to_bcs');

function xml_to_bcs($methods) {
    $methods['wp.uploadFile'] = 'xmlrpc_upload';
    $methods['metaWeblog.newMediaObject'] = 'xmlrpc_upload';
    return $methods;
}
//hook所有xmlrpc的上传
add_filter( 'xmlrpc_methods', 'xml_to_bcs' );
function xmlrpc_upload($args){
    $data  = $args[3];
		$name = sanitize_file_name( $data['name'] );
		$type = $data['type'];
		$bits = $data['bits'];
    require_once('bcs.class.php');
    $bcs_options = get_option('bcs_options', TRUE);
    $bcs_bucket = attribute_escape($bcs_options['bucket']);
    if(false === getenv ( 'HTTP_BAE_ENV_AK' )) {
	    $bcs_ak = attribute_escape($bcs_options['ak']);
    }
    if(false === getenv ( 'HTTP_BAE_ENV_SK' )) {
	    $bcs_sk = attribute_escape($bcs_options['sk']);
    }
    
	$baidu_bcs = new BaiduBCS($bcs_ak, $bcs_sk);


	$bucket = $bcs_bucket;
	$object =  "/" . $name;
	$opt = array(
		"acl" => "public-read"
	);
	$baidu_bcs->create_object_by_content ( $bucket, $object, $bits, $opt );
	$url = "http://bcs.duapp.com/{$bucket}{$object}"; 
	
	return array( 'file' => $url, 'url' => $url, 'type' => $data['type'] );
}

function format_bcs_url($url) {
	if(strpos($url, "http://bcs.duapp.com") !== false) {
		$arr = explode("http://bcs.duapp.com", $url);
		$url = "http://bcs.duapp.com" . $arr[1];
	}
	return $url;
}
add_filter('wp_get_attachment_url', 'format_bcs_url');

function bcs_plugin_action_links( $links, $file ) {
	if ( $file == plugin_basename( dirname(__FILE__).'/bcs-support.php' ) ) {
		$links[] = '<a href="options-general.php?page=' . BCS_BASEFOLDER . '/bcs-support.php">'.__('Settings').'</a>';
	}

	return $links;
}

add_filter( 'plugin_action_links', 'bcs_plugin_action_links', 10, 2 );

//删除BCS上的附件 Thanks Loveyuki（loveyuki@gmail.com）
function del_attachments_from_bcs($file) {
require_once('bcs.class.php');

$bcs_options = get_option('bcs_options', TRUE);

$bcs_bucket = attribute_escape($bcs_options['bucket']);

if(false === getenv ( 'HTTP_BAE_ENV_AK' )) {
$bcs_ak = attribute_escape($bcs_options['ak']);
}

if(false === getenv ( 'HTTP_BAE_ENV_SK' )) {
$bcs_sk = attribute_escape($bcs_options['sk']);
}

if(!is_object($baidu_bcs))
$baidu_bcs = new BaiduBCS($bcs_ak, $bcs_sk);

$bucket = $bcs_bucket;

$upload_dir = wp_upload_dir();

$object = str_replace($upload_dir['basedir'],'',$file);
$object = ltrim( $object , '/' );

$object = str_replace('http://bcs.duapp.com/'.$bucket,'',$object);

$baidu_bcs->delete_object($bcs_bucket,$object);

return $file;
}

add_action('wp_delete_file', 'del_attachments_from_bcs');

function bcs_add_setting_page() {
    add_options_page('BCS Setting', 'BCS Setting', 8, __FILE__, 'bcs_setting_page');
}

add_action('admin_menu', 'bcs_add_setting_page');

function bcs_setting_page() {

	$options = array();
	if($_POST['bucket']) {
		$options['bucket'] = trim(stripslashes($_POST['bucket']));
	}
	if($_POST['ak'] && false === getenv ( 'HTTP_BAE_ENV_AK' )) {
		$options['ak'] = trim(stripslashes($_POST['ak']));
	}
	if($_POST['sk'] && false === getenv ( 'HTTP_BAE_ENV_SK' )) {
		$options['sk'] = trim(stripslashes($_POST['sk']));
	}
	if($options !== array() ){
	
		update_option('bcs_options', $options);
        
?>
<div class="updated"><p><strong>设置已保存！</strong></p></div>
<?php
    }

    $bcs_options = get_option('bcs_options', TRUE);

    $bcs_bucket = attribute_escape($bcs_options['bucket']);
    $bcs_ak = attribute_escape($bcs_options['ak']);
    $bcs_sk = attribute_escape($bcs_options['sk']);
?>
<div class="wrap" style="margin: 10px;">
    <h2>百度云存储 设置</h2>
    <form name="form1" method="post" action="<?php echo wp_nonce_url('./options-general.php?page=' . BCS_BASEFOLDER . '/bcs-support.php'); ?>">
        <fieldset>
            <legend>Bucket 设置</legend>
            <input type="text" name="bucket" value="<?php echo $bcs_bucket;?>" placeholder="请输入云存储使用的 bucket"/>
            <p>请先访问 <a href="http://developer.baidu.com/bae/bcs/bucket/">百度云存储</a> 创建 bucket 后，填写以上内容。</p>
        </fieldset>
        <?php
        if ( false === getenv ( 'HTTP_BAE_ENV_AK' ) || false === getenv ( 'HTTP_BAE_ENV_SK' )) :
        ?>
        <fieldset>
            <legend>Access Key / API key</legend>
            <input type="text" name="ak" value="<?php echo $bcs_ak;?>" placeholder=""/>
            <p>访问 <a href="http://developer.baidu.com/bae/ref/key/" target="_blank">BAE 密钥管理页面</a>，获取 AKSK</p>
        </fieldset>
        <fieldset>
            <legend>Secret Key</legend>
            <input type="text" name="sk" value="<?php echo $bcs_sk;?>" placeholder=""/>
        </fieldset>
        <?php
        endif;
        ?>
        <fieldset class="submit">
            <legend>更新选项</legend>
            <input type="submit" name="submit" value="更新" />
        </fieldset>
    </form>
	<h2>赞助</h2>
		<p>如果你发现这个插件对你有帮助，欢迎<a href="https://me.alipay.com/gimhoy" target="_blank">赞助</a>!</p>
		<p><a href="https://me.alipay.com/gimhoy" target="_blank"><img src="http://archives.gimhoy.cn/archives/alipay_donate.png" alt="支付宝捐赠" title="支付宝" /></a></p>
	<br />
</div>
<?php
}
