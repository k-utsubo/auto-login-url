<?php
/**
 * Plugin Name:     Auto Login URL
 * Plugin URI:
 * Description:     generate a auto login URL for any user.
 * Author:          Katsuhiko Utsubo
 * Author URI:
 * Text Domain:     auto-login-url
 * Domain Path:
 * Version:         0.0.1
 *
 * @package         Auto_Login_URL
 */


$auto_login_url=new AutoLoginUrl(); 

class AutoLoginUrl
{
    public $TABLE;
    public $DBVER;
    public $PAGELIMIT;
    public function __construct()
    {
        $this->TABLE='autologin';
        $this->DBVER='1.0';
        $this->PAGELIMIT=10;
    

        register_activation_hook(__FILE__, array($this, 'plugin_activate'));
        //register_deactivation_hook(__FILE__, array($this, 'plugin_unactivate'));
        register_deactivation_hook(__FILE__,  array($this, 'plugin_uninstall'));
        //add_action( 'plugins_loaded', array($this, 'update_db_check') );

        add_action( 'init', array($this, 'handle_token') );
        add_action( 'admin_menu', array($this, 'add_pages'));
        add_action( 'cleanup_expired_tokens', array($this,'cleanup_expired_tokens'), 10, 2 );

        if(is_admin()) {
            //administrator setting menu
            add_action('admin_init', array(&$this, 'option_register'));
        }

     }

     function add_pages()
     {
         add_options_page(
              'Auto_Login_Url', // page_title（オプションページのHTMLのタイトル）
              'AutoLoginUrl', // menu_title（メニューで表示されるタイトル）
              'administrator', // capability
              'auto-login-url', // menu_slug（URLのスラッグこの例だとoptions-general.php?page=hello-world）
              array(&$this,'create_html_page') // function
         );
    }

    public function option_register()
    {
        error_log("option_register");
        register_setting('post6widget_optiongroup', 'post6widget_option');
        //load css file into <head> tag
        $urlpath = plugins_url('admin.css', __FILE__);
        wp_register_style('post6style', $urlpath);
        wp_enqueue_style('post6style');
    }

 


    /**
     * Handle cleanup process for expired auto login tokens.
     */
    function cleanup_expired_tokens( $user_id, $expired_tokens ) {
    	error_log("expired_tokens,user_id=".$user_id);
    	$tokens = get_user_meta( $user_id, 'auto_login_url_token', true );
    	$tokens = is_string( $tokens ) ? array( $tokens ) : $tokens;
    	$new_tokens = array();
    	foreach ( $tokens as $token ) {
    		if ( ! in_array( $token, $expired_tokens, true ) ) {
    			$new_tokens[] = $token;
    		}
    	}
    	update_user_meta( $user_id, 'auto_login_url_token', $new_tokens );
    }
    
    /**
     * Log a request in as a user if the token is valid.
     */
    function handle_token() {
    	global $pagenow;
    
    	if ( 'wp-login.php' !== $pagenow || empty( $_GET['user_id'] ) || empty( $_GET['auto_login_url_token'] ) ) {
    		return;
    	}
    
    	if ( is_user_logged_in() ) {
    		$error = sprintf( __( 'Invalid auto login token, but you are logged in as \'%s\'. <a href="%s">Go to the dashboard instead</a>?', 'auto-login-url' ), wp_get_current_user()->user_login, admin_url() );
    	} else {
    		$error = sprintf( __( 'Invalid auto login token. <a href="%s">Try signing in instead</a>?', 'auto-login-url' ), wp_login_url() );
    	}
    
    	// Ensure any expired crons are run
    	// It would be nice if WP-Cron had an API for this, but alas.
    	$crons = _get_cron_array();
    	if ( ! empty( $crons ) ) {
    		foreach ( $crons as $time => $hooks ) {
    			if ( time() < $time ) {
    				continue;
    			}
    			foreach ( $hooks as $hook => $hook_events ) {
    				if ( 'cleanup_expired_tokens' !== $hook ) {
    					continue;
    				}
    				foreach ( $hook_events as $sig => $data ) {
    					if ( ! defined( 'DOING_CRON' ) ) {
    						define( 'DOING_CRON', true );
    					}
    					do_action_ref_array( $hook, $data['args'] );
    					wp_unschedule_event( $time, $hook, $data['args'] );
    				}
    			}
    		}
    	}
    
    	// Use a generic error message to ensure user ids can't be sniffed
    	$user = get_user_by( 'id', (int) $_GET['user_id'] );
    	if ( ! $user ) {
    		wp_die( $error );
    	}
    
    	$tokens = get_user_meta( $user->ID, 'auto_login_url_token', true );
    	$tokens = is_string( $tokens ) ? array( $tokens ) : $tokens;
    	$is_valid = false;
    	$time=time();
    	foreach ( $tokens as $i => $token ) {
    		error_log("expire_date=".$token["expire_date"]);
    		error_log("expire_date=".strftime('%Y-%m-%d %H:%M:%S',$token["expire_date"]));
    		error_log("time=".strftime('%Y-%m-%d %H:%M:%S',$time));
    		if($token["expire_date"]!=0 and $token["expire_date"]<$time){
    			error_log("unset1");
    			unset($tokens[ $i ]);
    			continue;
    		}
    /*
    		if($token["to_date"]<$time){
    			error_log("unset3");
    			unset($tokens[ $i ]);
    			continue;
    		}
    */
    
    		//if ( hash_equals( $token["token"], $_GET['auto_login_url_token'] ) and $token["start_date"]<=$time and $time<=$token["to_date"]) {
    		if ( hash_equals( $token["token"], $_GET['auto_login_url_token'] ) and $token["start_date"]<=$time ) {
    			$is_valid = true;
    			if( $token["expire_date"]==0){
    				error_log("unset2");
    				unset( $tokens[ $i ] );
    			}
    			break;
    		}
    	}
    
    	update_user_meta( $user->ID, 'auto_login_url_token', $tokens );
    
    	if ( ! $is_valid ) {
    		wp_die( $error );
    	}
    
    	error_log("tokens=".var_dump($tokens));
    	do_action( 'auto_login_url_logged_in', $user );
    	//update_user_meta( $user->ID, 'auto_login_url_token', $tokens );
    	wp_set_auth_cookie( $user->ID, true, is_ssl() );
    
    	if ( $token["redirect_url"] ){
    		wp_safe_redirect( site_url().$token["redirect_url"] );
    	}else{
    		wp_safe_redirect( admin_url() );
    	}
    	exit;
    }
    



    /**
     * create html of adminstrator page
     */
    function create_html_page()
    {
        error_log("create_html_page");
        if (isset($_REQUEST["submit"]["detail"])) {
            //詳細
            self::detail();
        } else if (isset($_REQUEST["submit"]["edit"])) {
            //修正
            self::edit();
        } else if (isset($_REQUEST["submit"]["edit_check"])) {
            //修正確認
            self::edit_check();
        } else if (isset($_REQUEST["submit"]["edit_exec"])) {
            //修正実行
            self::edit_exec();
        } else if (isset($_REQUEST["submit"]["delete_check"])) {
            //削除確認
            self::delete_check();
        } else if (isset($_REQUEST["submit"]["delete_exec"])) {
            //削除実行
            self::delete_exec();
        } else if (isset($_REQUEST["submit"]["regist"])) {
            //新規登録
            self::regist();
        } else if (isset($_REQUEST["submit"]["regist_check"])) {
            //新規登録確認
            self::regist_check();
        } else if (isset($_REQUEST["submit"]["regist_exec"])) {
            //新規登録
            self::regist_exec();
        } else if (isset($_REQUEST["submit"]["regist_file"])) {
            //ファイルアップロード用画面
            self::regist_file();
        } else if (isset($_REQUEST["submit"]["regist_file_up"])) {
            //ファイルアップロード処理
            self::regist_file_up();
        } else {
            // initial display 
            self::disp();
        }
    }
 
    /**
     * initial display 
     */
    function disp()
    {
        error_log("disp");
        // data list
        echo <<< EOL
    <form action="" method="post">
     
    <h2>Data List</h2>
    <input type='submit' name='submit[regist]' class='button-primary' value='Register' />
    <input type='submit' name='submit[regist_file]' class='button-primary' value='Retister(Upload File)' />

     
    <div class="wrap">
     
    <table class="wp-list-table widefat striped posts">
        <tr>
            <th nowrap>ID</th>
            <th nowrap>user_id</th>
            <th nowrap>start_date</th>
            <th nowrap>expire_date</th>
            <th nowrap>detail</th>
            <th nowrap>delete</th>
        </tr>
EOL;
     
        $pageid = filter_input(INPUT_GET, 'pageid');
        //  display rows per a page
        $limit = $this->PAGELIMIT;

        global $wpdb;
     
        // get all data number
        $tbl_name = $wpdb->prefix . $this->TABLE;
        $sql = "SELECT count(*) AS CNT FROM {$tbl_name}";
        $rows = $wpdb->get_results($sql);
        $recordcount = $rows[0]->CNT;
        error_log("recordcount=".$recordcount);
        
        // decide offset value
        $offset = $pageid * $limit;
        error_log("offset=".$offset.",limit=".$limit);
        
        //create sql using offset and  limit
        $sql = "SELECT * FROM {$tbl_name} ORDER BY id limit {$offset}, {$limit}";
        // get data
        $rows = $wpdb->get_results($sql);

        foreach($rows as $row) {
            echo "<tr>";
            echo "<td>" . $row->id . "</td>";
            echo "<td>" . $row->user_id . "</td>";
            echo "<td>" . $row->start_date . "</td>";
            echo "<td>" . $row->expire_date . "</td>";
            echo "<td>";
            echo "<input type='submit' name='submit[detail][" . $row->id . "]'";
            echo " class='button-primary' value='detail' />";
            echo "</td>";
            echo "<td>";
            echo "<input type='submit' name='submit[delete][" . $row->id . "]'";
            echo " class='button-primary' value='delete' />";
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
        echo "</form>";

         
        
        $args = array(
            'label' => __('Per Page'),
            'default' => $this->PAGELIMIT,
            'option' => 'disp'
        );
        $page_html = self::pagination($recordcount);
        //ページ部分の表示
        echo "<div class='admin_pagination'>";
        echo "<ul>";
        foreach ($page_html as $key => $value) {
            echo "<li>" . $value . "</li>";
        }
        echo "</ul>";
    }

    function getCurrentPage(){
        $pageid = filter_input(INPUT_GET, 'pageid');
        if($pageid){
        return $pageid;
        }else{
            return 0;
        }
    }
 
    function pagination($recordcount)
    {
        $count = $recordcount;
        $limit = $this->PAGELIMIT;
     
        // if no record, do nothing
        if (0 === $count) {
            return '';
        }
     
        // current page number,  0-
        $intCurrentPage = self::getCurrentPage();
     
        // max page number
        $intMaxpage = ceil($count / $limit);
     
        // output 3 pages before/after current page
        $intStartpage = (2 < $intCurrentPage) ? $intCurrentPage - 3 : 0;
        $intEndpage = (($intStartpage + 7) < $intMaxpage) ? $intStartpage + 7 : $intMaxpage;
     
        //make url
        $urlparams = filter_input_array(INPUT_GET);
     
        $items = [];
     
        // create page url
        //first
        $urlparams['page'] = filter_input(INPUT_GET, 'page');
        $urlparams['pageid'] = 0;
        $items[] = sprintf('<span><a href="?%s">%s</a></span>'
            , http_build_query($urlparams)
            , 'First'
        );
     
        // when current page is not first page
        if (0 < $intCurrentPage) {
            $urlparams['pageid'] = $intCurrentPage - 1;
            $items[] = sprintf('<span><a href="?%s">%s</a></span>'
                , http_build_query($urlparams)
                , 'Prev'
            );
        }
     
        for ($i = $intStartpage; $i < $intEndpage; $i++) {
            $urlparams['pageid'] = $i;
            $items[] = sprintf('<span%s><a href="?%s">%s</a></span>'
                , ($intCurrentPage == $i) ? ' class="current"' : ''
                , http_build_query($urlparams)
                , $i + 1
            );
        }
     
        // when current page is not last page
        if ($intCurrentPage < $intMaxpage) {
            $urlparams['pageid'] = $intCurrentPage + 1;
            $items[] = sprintf('<span><a href="?%s">%s</a></span>'
                , http_build_query($urlparams)
                , 'Next'
            );
        }
     
        // last
        $urlparams['pageid'] = $intMaxpage - 1;
        $items[] = sprintf('<span><a href="?%s">%s</a></span>'
            , http_build_query($urlparams)
            , 'Last'
        );
     
        return $items;
    }

    // display detail page
    function detail()
    {
        // get pressed button id
        if (array_search("detail", $_REQUEST["submit"]["detail"])) {
            $form_id = array_search("detail", $_REQUEST["submit"]["detail"]);
        }
 
        // list of data
        echo <<< EOL
<form action="" method="post">
    <h2>Detail</h2>
EOL;
 
        global $wpdb;
 
        $tbl_name = $wpdb->prefix . $this->TABLE;
        $sql = "SELECT * FROM {$tbl_name} WHERE id = %d;";
        $prepared = $wpdb->prepare($sql, $form_id);
        $rows = $wpdb->get_results($prepared, ARRAY_A);
 
        echo <<<EOL
<table border="1">
    <tr>
        <td>User ID</td>
        <td>{$rows[0]["user_id"]}</td>
    </tr>
    <tr>
        <td>StartDate</td>
        <td>{$rows[0]["start_date"]}</td>
    </tr>
    <tr>
        <td>Expire Date</td>
        <td>{$rows[0]["expire_date"]}</td>
    </tr>
    <tr>
        <td>Redirect URL</td>
        <td>{$rows[0]["redirect_url"]}</td>
    </tr>
    <tr>
        <td>Secret Key</td>
        <td>{$rows[0]["secret_key"]}</td>
    </tr>
</table>
<input type="hidden" name="form_id" value={$rows[0]["form_id"]}>
<input type='submit' name='submit[]' class='button-primary' value='Back' />
EOL;
        echo "</form>";
    }
 
    function edit()
    {
        if (isset($_REQUEST["form_id"])) {
            $form_id = $_REQUEST["form_id"];
            $user_id = $_REQUEST["user_id"];
            $start_date = $_REQUEST["start_date"];
            $expire_date = $_REQUEST["expire_date"];
            $redirect_url = $_REQUEST["redirect_url"];
            $secret_key = $_REQUEST["secret_key"];
        } else {
            // get pushed button id 
            if (array_search("edit", $_REQUEST["submit"]["edit"])) {
                $form_id = array_search("edit", $_REQUEST["submit"]["edit"]);
            }
 
            global $wpdb;
 
            $tbl_name = $wpdb->prefix . $this->TABLE;
            $sql = "SELECT * FROM {$tbl_name} WHERE id = %d;";
            $prepared = $wpdb->prepare($sql, $form_id);
            $rows = $wpdb->get_results($prepared, ARRAY_A);
 
            $form_id = $rows[0]["form_id"];
            $user_id = $rows[0]["user_id"];
            $start_date = $rows[0]["start_date"];
            $expire_date = $rows[0]["expire_date"];
            $redirect_url = $rows[0]["redirect_url"];
            $secret_key = $rows[0]["secret_key"];
        }
 
        echo <<< EOL
<form action="" method="post">
    <h2>edit</h2>
EOL;
 
 
        echo <<<EOL
<table border="1">
    <tr>
        <td>user_id</td>
        <td>
            <input type="text" name="user_id" value="{$user_id}">
        </td>
    </tr>
    <tr>
        <td>start_date</td>
        <td>
            <input type="text" name="start_date" value="{$start_date}">
        </td>
    </tr>
    <tr>
        <td>expire_date</td>
        <td>
            <input type="text" name="expire_date" value="{$expire_date}">
        </td>
    </tr>
    <tr>
        <td>token</td>
        <td>
            <input type="text" name="seacret_key" value="{$seacret_key}">
        </td>
    </tr>
</table>
 
<input type="hidden" name="form_id" value="{$form_id}">
 
<input type='submit' name='submit[edit_check]' class='button-primary' value='registration confirmed' />
<input type='submit' name='submit[]' class='button-primary' value='back' />
EOL;
        echo "</form>";
    }
 
    function edit_check()
    {
        $form_id = $_REQUEST["form_id"];
        $user_id = $_REQUEST["user_id"];
        $start_date = $_REQUEST["start_date"];
        $expire_date = $_REQUEST["expire_date"];
        $redirect_url = $_REQUEST["redirect_url"];
        $secret_key = $_REQUEST["secret_key"];
 
        echo <<< EOL
<form action="" method="post">
    <h2>confirm</h2>
EOL;
 
        echo <<<EOL
<table border="1">
    <tr>
        <td>user_id</td>
        <td>{$user_id}</td>
    </tr>
    <tr>
        <td>start_date</td>
        <td>{$start_date}</td>
    </tr>
    <tr>
        <td>expire_date</td>
        <td>{$expire_date}</td>
    </tr>
    <tr>
        <td>seacret_key</td>
        <td>{$seacret_key}</td>
    </tr>
</table>
 
<input type="hidden" name="form_id" value="{$form_id}">
 
<input type='submit' name='submit[edit_exec]' class='button-primary' value='edit' />
<input type='submit' name='submit[edit]' class='button-primary' value='backる' />
EOL;
        echo "</form>";
    }
 
    function edit_exec()
    {
        global $wpdb;
 
        $form_id = $_REQUEST["form_id"];
        $user_id = $_REQUEST["user_id"];
        $update_date = date("Y-m-d H:i:s");
 
        //update post
        $tbl_name = $wpdb->prefix . $this->TABLE;
        $result = $wpdb->update(
            $tbl_name,
            array('user_id' => $user_id,),
            array('id' => $form_id,),
            array('%s'),
            array('%d')
        );
 
        // list of data
        echo <<< EOL
<form action="" method="post">
    <h2>registration confirmed</h2>
EOL;
 
        echo "<div class='updated fade'><p><strong>";
        echo _e('updated');
        echo "</strong></p></div>";
 
        echo <<<EOL
<input type='submit' name='submit[]' class='button-primary' value='back' />
EOL;
        echo "</form>";
    }
    /*
     * Confirm delete
     */
    function delete_check()
    {
        $form_id = $_REQUEST["form_id"];
 
        global $wpdb;
 
        $tbl_name = $wpdb->prefix . $this->TABLE;
        $sql = "SELECT * FROM {$tbl_name} WHERE id = %d;";
        $prepared = $wpdb->prepare($sql, $form_id);
        $rows = $wpdb->get_results($prepared, ARRAY_A);
 
        // data list
        echo <<< EOL
<form action="" method="post">
    <h2>Comfirm delete data</h2>
<table border="1">
    <tr>
        <td>User ID</td>
        <td>{$rows[0]["user_id"]}</td>
    </tr>
    <tr>
        <td>StartDate</td>
        <td>{$rows[0]["start_date"]}</td>
    </tr>
    <tr>
        <td>Expire Date</td>
        <td>{$rows[0]["expire_date"]}</td>
    </tr>
    <tr>
        <td>Redirect URL</td>
        <td>{$rows[0]["redirect_url"]}</td>
    </tr>
    <tr>
        <td>Secret Key</td>
        <td>{$rows[0]["secret_key"]}</td>
    </tr>
</table>
    <input type="hidden" name="form_id" value="{$form_id}">
 
    <input type='submit' name='submit[delete_exec]' class='button-primary' value='delete' />
    <input type='submit' name='submit[edit]' class='button-primary' value='Back' />
EOL;
        echo "</form>";
    }

    /**
     *  execute delete data
     */
    function delete_exec()
    {
        $form_id = $_REQUEST["form_id"];
 
        // delete data
        global $wpdb;
        $tbl_name = $wpdb->prefix . $this->TABLE;
        $sql = "DELETE FROM {$tbl_name} WHERE id = %s;";
        $dlt = $wpdb->query($wpdb->prepare($sql, $form_id));
 
        // delete 
        echo <<< EOL
<form action="" method="post">
    <h2>Finish delete data</h2>
EOL;
 
        echo "<div class='updated fade'><p><strong>";
        echo _e('Already deleted');
        echo "</strong></p></div>";
 
        echo <<<EOL
<input type='submit' name='submit[]' class='button-primary' value='Back' />
EOL;
        echo "</form>";
    }


    function regist()
    {
        $user_id="";
        if (isset($error_message_flg) and $error_message_flg !== false) {
            $user_id = esc_attr($_REQUEST["user_id"]);
        }
            
 
        echo <<< EOL
<form action="" method="post">
    <h2>データ登録</h2>
EOL;
 
        // if error_message_flg is false, show error message
        if (isset($error_message_flg) and $error_message_flg == false) {
            echo "<div class='updated fade'><p><strong>";
            echo _e('Please input user_id');
            echo "</strong></p></div>";
        }
 
        echo <<< EOL
    <table border="1">
        <tr>
            <td>NAME</td>
            <td>
                <input type="text" name="user_id" value="{$user_id}">
            </td>
        </tr>
    </table>
 
    <input type='submit' name='submit[regist_check]' class='button-primary' value='登録内容を確認する' />
    <input type='submit' name='submit[]' class='button-primary' value='戻る' />
</form>
EOL;
    }
 
    /**
     * 登録確認
     */
    function regist_check()
    {
        //入力値が空白の場合の処理(ここでは単純に0バイトだったら)
        if (!strlen($_REQUEST["user_id"])) {
            self::regist(false);
            return;
        }
         
        $user_id = esc_attr($_REQUEST["user_id"]);
 
        //データ一覧
        echo <<< EOL
<form action="" method="post">
    <h2>データ登録確認</h2>
<table border="1">
    <tr>
        <td>NAME</td>
        <td>{$user_id}</td>
    </tr>
</table>
 
<input type="hidden" name="user_id" value="{$user_id}">
 
<input type='submit' name='submit[regist_exec]' class='button-primary' value='登録する' />
<input type='submit' name='submit[regist]' class='button-primary' value='戻る' />
EOL;
        echo "</form>";
    }
 
    /**
     * 登録実行
     */
    function regist_exec()
    {
        global $wpdb;
 
        $user_id = $_REQUEST["user_id"];
 
        //投稿を登録
        $table_name = $wpdb->prefix . $this->TABLE;
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'create_date' => current_time('mysql')
            )
        );
 
        //データ一覧
        echo <<< EOL
<form action="" method="post">
    <h2>データ登録</h2>
    <div class='updated fade'><p><strong>
EOL;
        echo _e('登録が完了しました');
        echo <<<EOL
</strong></p></div>
<input type='submit' name='submit[]' class='button-primary' value='戻る' />
EOL;
        echo "</form>";
    }


    function regist_file($error_message_flg = null)
    {
        echo <<< EOL
    <h2>ファイルアップロード</h2>
    <div class="wrap">
    <div class="wrap">
    <table class="wp-list-table widefat striped posts">
        <tr>
            <td>ファイルアップロード</td>
            <td>
                <form method="post" action="" enctype="multipart/form-data">
                    CSVを選択してアップロードボタンを押してください<br />
                    <input type="file" name="upfilename" />
                    <input type="submit" value="アップロード">
                    <input type="hidden" name="submit[regist_file_up]" value="on">
                </form>
     
            </td>
        </tr>
    </table>
    </div>
    <form action="" method="post">
        <input type='submit' name='submit[file_upload]' class='button-primary' value='戻る' />
        <input type="hidden" name="form_id" value="{$form_id}">
        <input type="hidden" name="create_date" value="{$create_date}">
    </form>
    </div>
EOL;
     
    }
    
    function regist_file_up()
    {
        //CSVファイルがアップロードされた場合
        if (is_uploaded_file($_FILES["upfilename"]["tmp_name"])) {
            $upload_dir = wp_upload_dir();
            $upload_file_name = $upload_dir['basedir'] . "/" . $_FILES["upfilename"]["name"];
            if (move_uploaded_file($_FILES["upfilename"]["tmp_name"], $upload_file_name)) {
                chmod($upload_file_name, 0777);
            }
            $message = "ファイルをアップロードいたしました";
        } else {
            $message = "ファイルのアップロードが失敗しました";
        }
     
        //完了メッセージの出力
        echo <<< EOL
    <h2>ファイルアップロード完了</h2>
    <form action="" method="post">
    <div class="wrap">
        {$message}
    </div>
    <input type='submit' name='submit[]' class='button-primary' value='戻る' />
    </form>
EOL;
     
    }



    function plugin_activate()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->TABLE;
        $charset_collate = $wpdb->get_charset_collate();
     
        $sql = <<< EOL
CREATE TABLE {$table_name} (
id              INT NOT NULL AUTO_INCREMENT,
user_id     bigint(20) unsigned NOT NULL,
seacret_key    varchar(255) NOT NULL,
start_date     DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
expire_date     DATETIME DEFAULT '2038-01-18 00:00:00' NOT NULL,
redirect_url    varchar(255) DEFAULT '' NOT NULL,
PRIMARY KEY(id)
) {$charset_collate};
EOL;
     
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        add_option("auto_login_url_db_version",$this->DBVER);
    }

    /**
    *  call when plugin stopped
    */
//    function plugin_unactivate()
//    {
//        // delete table data
//        global $wpdb;
//     
//        // determin table name
//        $table_name = $wpdb->prefix . $this->TALBE;
//     
//        // delete table data
//        $sql_delete = "DELETE FROM " . $table_name.";";
//        $wpdb->query($sql_delete);
//    }


    // check database version when plugin updated
    function update_db_check()
    {
         //$version=get_site_option( 'auto_login_url_db_version' );
         //if ( $version or $version!= $this->DBVER ) {
         //      $this->plugin_activate();
         //}
    }

    /**
     * call when plugin uninstalled
     */
    function plugin_uninstall()
    {
        global $wpdb;
        error_log("plugin_uninstall");
        delete_option('auto_login_url_db_version');
        $table_name = $wpdb->prefix . $this->TABLE;
        $sql_drop = 'DROP TABLE ' . $table_name ;
        error_log($sql_drop);
        $wpdb->query($sql_drop);

    }
}
 
